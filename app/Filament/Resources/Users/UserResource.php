<?php

namespace App\Filament\Resources\Users;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Radio;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Maatwebsite\Excel\Excel;
use Filament\Actions\BulkAction;
use App\Events\OnAccountRestricted;
use App\Filament\Resources\Users\RelationManagers\RolesRelationManager;
use App\Filament\Resources\Users\RelationManagers\EngagementHistoryRelationManager;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\ViewUsers;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Article;
use App\Models\Location;
use App\Models\LocationRating;
use App\Models\UserBlock;
use Closure;
use Filament\Forms;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables;
use App\Models\Reward;
use App\Models\Approval;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Str;
use Filament\Tables\Table;
use App\Services\PointService;
use App\Models\ApprovalSetting;
use App\Models\RewardComponent;
use Filament\Resources\Resource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\Select;
use App\Services\PointComponentService;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\SelectColumn;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Relations\Relation;
use Filament\Forms\Components\Actions\Action;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Finder\Iterator\DateRangeFilterIterator;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class UserResource extends Resource
{
    protected $user;

    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static string | \UnitEnum | null $navigationGroup = 'Users';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        TextInput::make('name')
                            ->autofocus()
                            ->required()
                            ->rules('required', 'max:255'),

                        // username
                        TextInput::make('username')
                            ->required()
							->unique(ignoreRecord: true)
                            // transform lowercaser and remove spaces
                            ->afterStateHydrated(function ($component, $state) {
                                $component->state(Str::slug($state));
                            })
                            ->rules('required', 'max:255', 'unique:users,username'),

                        TextInput::make('email')
                            ->required()
                            ->rules('required', 'email', 'max:255', 'unique:users,email'),

                        TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->rules( 'min:8', 'max:255'),


                        // status
                        Select::make('status')
                            ->options([
                                1 => 'Active',
                                2 => 'Suspended',
                                3 => 'Archived',
                            ])
                            ->default(1)
                            ->required(),

                        // account_restricted
                        Toggle::make('account_restricted')
                            ->helperText('If user is restricted, they cannot checkout merchant offers')
                            ->default(0),

                        // account_restricted_until
                        DateTimePicker::make('account_restricted_until')
                            ->helperText('If user is restricted, they cannot checkout merchant offers'),

                        // for_engagement
                        Toggle::make('for_engagement')
                            ->helperText('If user is used for engagement, they cannot login App.')
                            ->default(0)
                            ->required(),

                        // suspended until
                        DateTimePicker::make('suspended_until')
                            ->afterStateHydrated(function ($component, $state) {
                                // if this is set ensure status is Suspended
                                if ($state) {
                                    $component->parent()->components->status->state(2);
                                }
                            })
                            ->rules('nullable', 'date'),

                        DateTimePicker::make('email_verified_at')
                            ->rules('nullable', 'date'),

                        // inline phone_country_code and phone_no without labels but placeholders textinputs
                        Fieldset::make('Phone Number')
                            ->schema([
                                TextInput::make('phone_country_code')
                                    ->placeholder('60')
                                    ->label('')
                                    ->afterStateHydrated(function ($component, $state) {
                                        // ensure no symbols only numbers
                                        $component->state(preg_replace('/[^0-9]/', '', $state));
                                    })
                                    ->rules('nullable', 'max:255')->columnSpan(['lg' => 1]),
                                TextInput::make('phone_no')
                                    ->placeholder('eg. 123456789')
                                    ->label('')
                                    ->afterStateHydrated(function ($component, $state) {
                                        // ensure no symbols only numbers
                                        $component->state(preg_replace('/[^0-9]/', '', $state));
                                    })
                                    ->rules('nullable', 'max:255')->columnSpan(['lg' => 3]),
                            ])->columns(4),

                        // otp_verified_at
                        DateTimePicker::make('otp_verified_at')
                            ->label('Phone No OTP Verified At')
                            ->helperText('If user OTP is not verified they cannot login to App')
                            ->rules('date'),

                    ])->columnSpan(['lg' => 2]),
                Group::make()
                    ->schema([
                        // avatar
                        SpatieMediaLibraryFileUpload::make('avatar')
                            ->maxFiles(1)
                            ->nullable()
                            // disk is s3_public
                            ->disk(function () {
                                if (config('filesystems.default') === 's3') {
                                    return 's3_public';
                                }
                            })
                            ->collection('avatar'),

                        // bio
                        Textarea::make('bio')
                            ->rules('nullable', 'max:255'),

                        // date of birth
                        DatePicker::make('dob')
                            ->label('Date of Birth')
                            ->rules('nullable'),

                        // gender radio
                        Radio::make('gender')
                            ->inline()
                            ->options([
                                'male' => 'Male',
                                'female' => 'Female'
                            ])
                            ->rules('nullable'),

                        // job_title
                        TextInput::make('job_title')
                            ->rules('nullable', 'max:255'),

                        // has_article_personalization
                        Toggle::make('has_article_personalization')
                            ->label('Has Article Personalization?')
                            ->default(false),

                        Toggle::make('registered_with_merchant_crm')
                            ->label('Registered with Merchant CRM Form?')
                            ->default(false),

                        // field set location
                        Fieldset::make('Location')
                            ->schema([
                                // country relationship select searcheable nullable
                                Select::make('country_id')
                                    ->label('Country')
                                    ->relationship('country', 'name')
                                    ->preload()
                                    ->nullable()
                                    ->rules('nullable'),

                                // state relationship select searcheable nullable
                                Select::make('state_id')
                                    ->label('State')
                                    ->relationship('state', 'name')
                                    ->preload()
                                    ->nullable()
                                    ->rules('nullable'),
                            ])

                    ])->columnSpan(['lg' => 2]),
            ])->columns(4);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make(name: 'id')
                    ->searchable(),
                TextColumn::make(name: 'name')
                    ->searchable(),
                TextColumn::make(name: 'username')
                    ->searchable(),
                // status
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        1 => 'Active',
                        2 => 'Suspended',
                        3 => 'Archived',
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        1 => 'success',
                        2 => 'danger',
                        3 => 'secondary',
                        default => 'gray',
                    }),

                // account restricted
                TextColumn::make('account_restricted')
                    ->label('Account Restricted')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        0 => 'No',
                        1 => 'Yes',
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        0 => 'success',
                        1 => 'danger',
                        default => 'gray',
                    }),

                // account restricted until
                TextColumn::make('account_restricted_until')
                    ->label('Account Restricted Until')
                    ->dateTime(),

                TextColumn::make('full_phone_no')
                    ->label('Phone No')
                    ->searchable(['phone_country_code','phone_no'])
                    ->sortable(['phone_country_code','phone_no']),
                TextColumn::make(name: 'email')->searchable(),
                TextColumn::make(name: 'email_verified_at'),
                TextColumn::make('profile_is_private')
                    ->label('Profile Privacy')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        false => 'Public',
                        true => 'Private',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match($state) {
                        false => 'success',
                        true => 'danger',
                        default => 'gray',
                    }),
                // has_article_personalization
                TextColumn::make('has_article_personalization')
                    ->label('Has Article Personalization?')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No')
                    ->color(fn ($state) => $state ? 'success' : 'danger'),

                TextColumn::make('registered_with_merchant_crm')
                    ->label('Registered Merchant Form?')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No')
                    ->color(fn ($state) => $state ? 'success' : 'danger'),

                // referred_by_id
                TextColumn::make('referredBy.name')
                    ->sortable()
                    ->label('Referred By'),

                TextColumn::make('point_balance')
                    ->sortable()
                    ->label('Funhub Balance'),
                TextColumn::make('total_engagement')
                    ->label('Total Engagement')
                    ->formatStateUsing(fn($record) => $record->interactions()->count()),
                TextColumn::make('created_at')->dateTime()
            ])
            ->filters([
                // filter by registered_with_merchant_crm
                SelectFilter::make('registered_with_merchant_crm')
                    ->label('Registered with Merchant CRM Form?')
                    ->options([
                        '0' => 'No',
                        '1' => 'Yes',
                    ]),

                // filter by status
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        2 => 'Suspended',
                        3 => 'Archived',
                    ]),
                Filter::make('referred_by')
                    ->schema([
                        TextInput::make('referred_by')
                            ->label('Referred By (ID, Username, or Name)')
                            ->placeholder('Enter ID, Username, or Name')
                    ])
                    ->query(function (Builder $query, array $data) {
                        $searchTerm = $data['referred_by'];

                        if ($searchTerm) {
                            $query->whereHas('referredBy', function (Builder $subQuery) use ($searchTerm) {
                                $subQuery->where('id', $searchTerm)
                                    ->orWhere('username', 'like', "%$searchTerm%")
                                    ->orWhere('name', 'like', "%$searchTerm%");
                            });
                        }
                    })
                    ->label('Referred By'),
                Filter::make('created_from')
                    ->schema([
                        DatePicker::make('created_from')
                            ->placeholder('Select start date'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['created_from']) {
                            $query->whereDate('created_at', '>=', $data['created_from']);
                        }
                    })
                    ->label('Created From'),

                Filter::make('created_until')
                    ->schema([
                        DatePicker::make('created_until')
                            ->placeholder('Select end date'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['created_until']) {
                            $query->whereDate('created_at', '<=', $data['created_until']);
                        }
                    })
                    ->label('Created Until'),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('View')
                    ->action(function ($record) {
                        return redirect(UserResource::getUrl('view', ['record' => $record->id]));
                    })
                    ->icon('heroicon-s-eye'),
                EditAction::make(),
            ])
            ->toolbarActions([
				DeleteBulkAction::make()
					->action(function (Collection $records) {
						foreach ($records as $user) {
							// Archive all articles by this user
							$user->articles()->update([
								'status' => Article::STATUS_ARCHIVED
							]);

							// Remove user from any UserBlock
							UserBlock::where('blockable_id', $user->id)
								->where('blockable_type', User::class)
								->delete();

							// Delete user's article ranks
							$user->articleRanks()->delete();

							// Delete user's location ratings
							$locationRatings = LocationRating::where('user_id', $user->id)->get();
							$locationIdsNeedRecalculateRatings = $locationRatings->pluck('location_id')->toArray();

							// Recalculate location average ratings
							Location::whereIn('id', $locationIdsNeedRecalculateRatings)->get()->each(function ($location) {
								$location->average_ratings = $location->ratings()->avg('rating');
								$location->save();
							});

							// Remove user_id from scout index
							$user->unsearchable();

							// Add a new record for account deletion for backup purposes
							$user->userAccountDeletion()->create([
								'reason' => 'Deleted from admin panel',
								'name' => $user->name,
								'username' => $user->username,
								'email' => $user->email,
								'phone_no' => $user->phone_no,
								'phone_country_code' => $user->phone_country_code,
							]);

							Log::info('User Account Bulk Deleted from Admin Portal', ['user_id' => $user->id]);

							$user->name = null;
							$user->username = null;
							$user->phone_no = null;
							$user->phone_country_code = null;
							$user->email = null;
							$user->password = null;
							$user->status = User::STATUS_ARCHIVED;
							$user->google_id = null;
							$user->facebook_id = null;
							$user->apple_id = null;
							$user->save();
						}
					}),

                ExportBulkAction::make()->exports([
                    ExcelExport::make('table')
						->fromTable()
						->withChunkSize(500)
						->withFilename(fn ($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
						->withWriterType(Excel::CSV),
                ]),

                // bulk action for account restricted
                BulkAction::make('Toggle Account Restricted')
                    ->label('Toggle Account Restricted')
                    ->action(function (Collection $records, array $data): void {
                        foreach ($records as $record) {
                            $previousRestricted = $record->account_restricted;
                            $previousRestrictedUntil = $record->account_restricted_until;
                            $record->account_restricted = $data['account_restricted'];
                            $record->account_restricted_until = $data['account_restricted_until'];
                            $record->save();

                            // Dispatch event for notification & cache clearing
                            event(new OnAccountRestricted(
                                $record,
                                $previousRestricted,
                                $previousRestrictedUntil,
                                $record->account_restricted,
                                $record->account_restricted_until
                            ));
                        }
                    })
                    ->schema([
                      Select::make('account_restricted')
                            ->label('Account Restricted')
                            ->options([
                                0 => 'No',
                                1 => 'Yes',
                            ])
                            ->required(),

                        DateTimePicker::make('account_restricted_until')
                            ->label('Account Restricted Until')
                            ->requiredIf('account_restricted', 1)
                    ])
                    ->requiresConfirmation()->deselectRecordsAfterCompletion(),

                BulkAction::make('Toggle Profile Private')
                    ->label('Toggle Profile Private')
                    ->action(function (Collection $records, array $data): void {
                        foreach ($records as $record) {
                            $latestSettings = $record->profilePrivacySettings()->orderBy('id', 'desc')->first();

                            $record->profilePrivacySettings()->create([
                                'profile' => $data['profile_privacy'],
                                'articles' => ($latestSettings) ? $latestSettings->articles : 'public',
                            ]);
                        }
                    })
                    ->schema([
                        Select::make('profile_privacy')
                            ->label('Profile Privacy')
                            ->options([
                                'public' => 'Public',
                                'private' => 'Private',
                            ])
                            ->required(),
                    ])
                    ->requiresConfirmation()->deselectRecordsAfterCompletion(),

                // Tables\Actions\DeleteBulkAction::make(),
                BulkAction::make('Unsuspend User')
                    ->action(function (Collection $records) {
                        $records->each(function (User $record) {
                            $record->update(['status' => User::STATUS_ACTIVE]);
                        });
                    })->requiresConfirmation()->deselectRecordsAfterCompletion(),
                BulkAction::make('Suspend User')
                    ->action(function (Collection $records) {
                        $records->each(function (User $record) {
                            $record->update(['status' => User::STATUS_SUSPENDED]);
                        });
                    })->requiresConfirmation()->deselectRecordsAfterCompletion(),
                BulkAction::make('Reset All Mission Progress')
               ->action(function (Collection $records, array $data): void {
                        $resetCount = 0;
                        foreach ($records as $record) {
                            $missionIds = $record->missionsParticipating()->pluck('mission_id')->toArray();

                            $record->newbie_missions_completed_at = null;
                            $record->save();
                            
                            Log::info('[UserResource] Resetting mission progress for user: ' . $record->id, [
                                'missions' => $missionIds,
                                'reseted_by' => auth()->user()->id,
                            ]);
                            
                            // delete mission progress
                            DB::table('missions_users')->where('user_id', $record->id)->delete();
                            
                            // clear FCM notification cache for each mission
                            foreach ($missionIds as $missionId) {
                                $cacheKey = 'fcm_notification_mission_' . $missionId . '_user_' . $record->id;
                                Cache::forget($cacheKey);
                                Log::info("Cleared FCM notification cache for mission {$missionId} and user {$record->id}");
                            }
                            
                            $resetCount++;
                        }
                        if ($resetCount > 0) {
                            Notification::make()
                            ->success()
                            ->title('Successfully reset mission progress of '.$resetCount.' users')
                            ->send();
                        }
                    })
                    ->requiresConfirmation(),
                BulkAction::make('reward')
                    ->label('Reward')
                    ->action(function (Collection $records, array $data): void {
                        foreach ($records as $record) {
                            $rewardType = $data['rewardType'];
                            $quantity = $data['quantity'];
                            $reward = Reward::first();
                            $rewardComponentId = $data['rewardComponent'] ? $data['rewardComponent'] : null;
                            $rewardComponent = RewardComponent::find($rewardComponentId);
                            $user = User::find($record->id);

                            switch ($rewardType) {
                                case 'point':
                                    $approvableType = 'App\Models\Reward';
                                    $approvableId = $reward->id;
                                    break;
                                case 'point_component':
                                    $approvableType = 'App\Models\RewardComponent';
                                    $approvableId = $rewardComponent->id;
                                    break;
                            }

                            $approvalSettings = ApprovalSetting::getSettingsForModel($approvableType);

                            foreach ($approvalSettings as $approvalSetting) {
                                // Create new approval record(s)
                                // Number of record(s) created based on no. of sequence available for each approvable_type
                                $approval = new Approval([
                                    'approval_setting_id' => $approvalSetting->id,
                                    'approver_id' => $approvalSetting->sequence === 1 ? auth()->user()->id : null,
                                    'approvable_type' => $approvableType,
                                    'approvable_id' => $approvableId,
                                    'data' => json_encode([
                                        'user' => $user->toArray(),
                                        'reward_user_id' => $user->id,
                                        'action' => 'reward-user',
                                        'quantity' => $quantity,
                                    ]),
                                    'approved' => false
                                ]);
                                $approval->save();
                            }
                        }
                    })
                    ->schema([
                        Select::make('rewardType')
                            ->label('Reward Type')
                            ->options([
                                'point' => 'Point',
                                'point_component' => 'Point Component',
                            ])
                            ->required(),
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->minValue(1)
                            ->integer()
                            ->required(),
                        Select::make('rewardComponent')
                            ->label('Reward Component')
                            ->options(RewardComponent::all()->pluck('name', 'id'))

                    ])->deselectRecordsAfterCompletion(),
                BulkAction::make('resetTutorialProgress')
                    ->label('Reset Tutorial Progress')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function ($records) {
                        $records->each(function ($user) {
                            $user->tutorialCompletions()->delete();
                        });

                        Notification::make()
                            ->title('Tutorial progress reset successfully')
                            ->success()
                            ->send();
                    }),

            ]);
    }

    public static function getRelations(): array
    {
        return [
            RolesRelationManager::class,
            AuditsRelationManager::class,
            EngagementHistoryRelationManager::class, // Add this line
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUsers::route('/{record}/view'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
