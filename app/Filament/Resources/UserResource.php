<?php

namespace App\Filament\Resources;

use Closure;
use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use App\Models\Reward;
use App\Models\Approval;
use Illuminate\Support\Str;
use Filament\Resources\Form;
use Filament\Resources\Table;
use App\Services\PointService;
use App\Models\ApprovalSetting;
use App\Models\RewardComponent;
use Filament\Resources\Resource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\Select;
use App\Services\PointComponentService;
use Filament\Tables\Actions\BulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\SelectColumn;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use App\Filament\Resources\UserResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Filament\Resources\UserResource\RelationManagers;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Tables\Filters\SelectFilter;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class UserResource extends Resource
{
    protected $user;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->autofocus()
                            ->required()
                            ->rules('required', 'max:255'),

                        // username
                        Forms\Components\TextInput::make('username')
                            ->required()
                            // transform lowercaser and remove spaces
                            ->afterStateHydrated(function ($component, $state) {
                                $component->state(Str::slug($state));
                            })
                            ->rules('required', 'max:255', 'unique:users,username'),

                        Forms\Components\TextInput::make('email')
                            ->required()
                            ->rules('required', 'email', 'max:255', 'unique:users,email'),

                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->rules( 'min:8', 'max:255'),


                        // status
                        Forms\Components\Select::make('status')
                            ->options([
                                1 => 'Active',
                                2 => 'Suspended',
                                3 => 'Archived',
                            ])
                            ->default(1)
                            ->required(),

                        // for_engagement
                        Forms\Components\Toggle::make('for_engagement')
                            ->helperText('If user is used for engagement, they cannot login App.')
                            ->default(0)
                            ->required(),

                        // suspended until
                        Forms\Components\DateTimePicker::make('suspended_until')
                            ->afterStateHydrated(function ($component, $state) {
                                // if this is set ensure status is Suspended
                                if ($state) {
                                    $component->parent()->components->status->state(2);
                                }
                            })
                            ->rules('nullable', 'date'),

                        Forms\Components\DateTimePicker::make('email_verified_at')
                            ->rules('nullable', 'date'),

                        // inline phone_country_code and phone_no without labels but placeholders textinputs
                        Forms\Components\Fieldset::make('Phone Number')
                            ->schema([
                                Forms\Components\TextInput::make('phone_country_code')
                                    ->placeholder('60')
                                    ->label('')
                                    ->afterStateHydrated(function ($component, $state) {
                                        // ensure no symbols only numbers
                                        $component->state(preg_replace('/[^0-9]/', '', $state));
                                    })
                                    ->rules('nullable', 'max:255')->columnSpan(['lg' => 1]),
                                Forms\Components\TextInput::make('phone_no')
                                    ->placeholder('eg. 123456789')
                                    ->label('')
                                    ->afterStateHydrated(function ($component, $state) {
                                        // ensure no symbols only numbers
                                        $component->state(preg_replace('/[^0-9]/', '', $state));
                                    })
                                    ->rules('nullable', 'max:255')->columnSpan(['lg' => 3]),
                            ])->columns(4),

                        // otp_verified_at
                        Forms\Components\DateTimePicker::make('otp_verified_at')
                            ->label('Phone No OTP Verified At')
                            ->helperText('If user OTP is not verified they cannot login to App')
                            ->rules('date'),

                    ])->columnSpan(['lg' => 2]),
                Forms\Components\Group::make()
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
                        Forms\Components\Textarea::make('bio')
                            ->rules('nullable', 'max:255'),

                        // date of birth
                        Forms\Components\DatePicker::make('dob')
                            ->label('Date of Birth')
                            ->rules('nullable'),

                        // gender radio
                        Forms\Components\Radio::make('gender')
                            ->inline()
                            ->options([
                                'male' => 'Male',
                                'female' => 'Female'
                            ])
                            ->rules('nullable'),

                        // job_title
                        Forms\Components\TextInput::make('job_title')
                            ->rules('nullable', 'max:255'),

                        // has_article_personalization
                        Forms\Components\Toggle::make('has_article_personalization')
                            ->label('Has Article Personalization?')
                            ->default(false),

                        // field set location
                        Forms\Components\Fieldset::make('Location')
                            ->schema([
                                // country relationship select searcheable nullable
                                Forms\Components\Select::make('country_id')
                                    ->label('Country')
                                    ->relationship('country', 'name')
                                    ->preload()
                                    ->nullable()
                                    ->rules('nullable'),

                                // state relationship select searcheable nullable
                                Forms\Components\Select::make('state_id')
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
                Tables\Columns\TextColumn::make(name: 'name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make(name: 'username')->sortable()->searchable(),
                // status
                Tables\Columns\BadgeColumn::make('status')
                    ->enum([
                        1 => 'Active',
                        2 => 'Suspended',
                        3 => 'Archived',
                    ])
                    ->colors([
                        'success' => 1,
                        'danger' => 2,
                        'secondary' => 3,
                    ])
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('full_phone_no')->label('Phone No'),
                Tables\Columns\TextColumn::make(name: 'email')->sortable()->searchable(),
                Tables\Columns\TextColumn::make(name: 'email_verified_at')->sortable(),
                Tables\Columns\BadgeColumn::make('profile_is_private')
                    ->label('Profile Privacy')
                    ->enum([
                        false => 'Public',
                        true => 'Private',
                    ])
                    ->colors([
                        'success' => false,
                        'danger' => true,
                    ]),
                // has_article_personalization
                Tables\Columns\BadgeColumn::make('has_article_personalization')
                    ->label('Has Article Personalization?')
                    ->enum([
                        false => 'No',
                        true => 'Yes',
                    ])
                    ->colors([
                        'success' => false,
                        'danger' => true,
                    ]),
                // referred_by_id
                Tables\Columns\TextColumn::make('referredBy.name')
                    ->label('Referred By'),

                Tables\Columns\TextColumn::make('point_balance')
                    ->label('Funhub Balance'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()
            ])
            ->filters([
                // filter by status
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        2 => 'Suspended',
                        3 => 'Archived',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('View')
                    ->action(function ($record) {
                        return redirect(UserResource::getUrl('view', ['record' => $record->id]));
                    })
                    ->icon('heroicon-s-eye'),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),

                ExportBulkAction::make()->exports([
                    ExcelExport::make('table')->fromTable(),
                ]),

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
                    ->form([
                        Select::make('profile_privacy')
                            ->label('Profile Privacy')
                            ->options([
                                'public' => 'Public',
                                'private' => 'Private',
                            ])
                            ->required(),
                    ])
                    ->requiresConfirmation(),

                // Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\BulkAction::make('Unsuspend User')
                    ->action(function (Collection $records) {
                        $records->each(function (User $record) {
                            $record->update(['status' => User::STATUS_ACTIVE]);
                        });
                    })->requiresConfirmation(),
                Tables\Actions\BulkAction::make('Suspend User')
                    ->action(function (Collection $records) {
                        $records->each(function (User $record) {
                            $record->update(['status' => User::STATUS_SUSPENDED]);
                        });
                    })->requiresConfirmation(),
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
                    ->form([
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
                    ])
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RolesRelationManager::class,
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUsers::route('/{record}/view'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
