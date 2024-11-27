<?php

namespace App\Filament\Resources;

use Closure;
use Carbon\Carbon;
use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use App\Models\Article;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Filament\Resources\Form;
use App\Models\MerchantOffer;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use App\Models\SystemNotification;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\SystemNotificationResource\Pages;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\SystemNotificationResource\RelationManagers;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Radio;
use Filament\Tables\Columns\BadgeColumn;

class SystemNotificationResource extends Resource
{
    protected static ?string $model = SystemNotification::class;

    protected static ?string $navigationGroup = 'Notification';

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        KeyValue::make('title')
                        ->columnSpan('full')
                        ->label('Title')
                        ->keyLabel('Language')
                        ->valueLabel('Translation')
                        ->required()
                        ->rules([
                            function () {
                                return function (string $attribute, $value, Closure $fail) {
                                    // check if word count from $value array is more than 50
                                    foreach ($value as $v) {
                                        $wordCount = str_word_count($v);

                                        if ($wordCount >50 ) {
                                            $fail('The :attribute cannot exceed 50 words');
                                        }
                                    }
                                };
                            }
                        ])
                        ->disableAddingRows()
                        ->disableDeletingRows()
                        ->disableEditingKeys()
                        ->afterStateHydrated(function ($context, $state, callable $set, $record) {
                            // Retrieve available locales
                            $locales = config('app.available_locales', []);

                            // If in edit context, retrieve the existing translations from the database
                            if ($context === 'edit' && $record) {
                                $translations = json_decode($record->title ?? [], true);

                                // Map available locales to keys of KeyValue component with corresponding values
                                foreach ($locales as $locale => $language) {
                                    // Search for the key (language code) corresponding to the current language name
                                    $languageCode = array_search($language, $locales);

                                    // Set the value for the corresponding key and value in the state
                                    $set("title.$language", $translations[$languageCode] ?? '');
                                }
                            } else {
                                // For other contexts or new records, map available locales to keys of KeyValue component with empty values
                                foreach ($locales as $locale => $language) {
                                    // Set the value for the corresponding key in the state
                                    $set("title.$language", '');
                                }
                            }
                        })
                        ->dehydrateStateUsing(function ($state) {
                            // Retrieve available locales
                            $locales = config('app.available_locales', []);

                            $transformedState = [];
                            if ($state == null) {
                                return json_encode($transformedState);
                            }
                            // Iterate over the keys in $state
                            foreach ($state as $key => $value) {
                                // Search for the corresponding key in $locales
                                $localeKey = array_search($key, $locales);

                                // If a corresponding key is found, use it to replace the key in $state
                                if ($localeKey !== false) {
                                    $transformedState[$localeKey] = $value;
                                }
                            }

                            // Convert the transformed state to JSON
                            $stateJson = json_encode($transformedState);

                            return $stateJson;
                        }),


                        // Textarea::make('content')
                        //     ->label('Content')
                        //     ->columnSpan('full')
                        //     ->rules([
                        //         function () {
                        //             return function (string $attribute, $value, Closure $fail) {
                        //                 $wordCount = str_word_count($value);

                        //                 if ($wordCount >50 ) {
                        //                     $fail('The :attribute cannot exceed 50 words');
                        //                 }
                        //             };
                        //         }
                        //     ])
                        //     ->required(),

                        KeyValue::make('content')
                            ->columnSpan('full')
                            ->label('Content')
                            ->keyLabel('Language')
                            ->valueLabel('Translation')
                            ->required()
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, Closure $fail) {
                                        // check if word count from $value array is more than 50
                                        foreach ($value as $v) {
                                            $wordCount = str_word_count($v);

                                            if ($wordCount >50 ) {
                                                $fail('The :attribute cannot exceed 50 words');
                                            }
                                        }
                                    };
                                }
                            ])
                            ->disableAddingRows()
                            ->disableDeletingRows()
                            ->disableEditingKeys()
                            ->afterStateHydrated(function ($context, $state, callable $set, $record) {
                                // Retrieve available locales
                                $locales = config('app.available_locales', []);

                                // If in edit context, retrieve the existing translations from the database
                                if ($context === 'edit' && $record) {
                                    // Fetch the existing translations for this record
                                    $translations = json_decode($record->content ?? [], true);

                                    // Map available locales to keys of KeyValue component with corresponding values
                                    foreach ($locales as $locale => $language) {
                                        // Search for the key (language code) corresponding to the current language name
                                        $languageCode = array_search($language, $locales);

                                        // Set the value for the corresponding key and value in the state
                                        $set("content.$language", $translations[$languageCode] ?? '');
                                    }
                                } else {
                                    // For other contexts or new records, map available locales to keys of KeyValue component with empty values
                                    foreach ($locales as $locale => $language) {
                                        // Set the value for the corresponding key in the state
                                        $set("content.$language", '');
                                    }
                                }
                            })
                            ->dehydrateStateUsing(function ($state) {
                                // Retrieve available locales
                                $locales = config('app.available_locales', []);

                                $transformedState = [];
                                if ($state == null) {
                                    return json_encode($transformedState);
                                }
                                // Iterate over the keys in $state
                                foreach ($state as $key => $value) {
                                    // Search for the corresponding key in $locales
                                    $localeKey = array_search($key, $locales);

                                    // If a corresponding key is found, use it to replace the key in $state
                                    if ($localeKey !== false) {
                                        $transformedState[$localeKey] = $value;
                                    }
                                }

                                // Convert the transformed state to JSON
                                $stateJson = json_encode($transformedState);

                                return $stateJson;
                            }),

                        DateTimePicker::make('scheduled_at')
                            ->label('Schedule Blast Time')
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, Closure $fail) {
                                        if (now()->greaterThan(Carbon::parse($value))) {
                                            $fail('The :attribute cannot be in the past');
                                        }
                                    };
                                }
                            ])
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Card::make()
                    ->schema([
                        Radio::make('redirect_type')
                            ->label('Redirect Type')
                            ->options(SystemNotification::REDIRECT_TYPE)
                            ->default(SystemNotification::REDIRECT_STATIC)
                            ->reactive()
                            ->required()
                            ->columnSpanFull(),

                        MorphToSelect::make('content')
                            ->label('Dynamic Redirect')
                            ->types([
                                MorphToSelect\Type::make(Article::class)
                                    ->label('Article')
                                    ->titleColumnName('title'),
                                MorphToSelect\Type::make(MerchantOffer::class)
                                    ->label('Deal')
                                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->name} " . "(" . (Carbon::parse($record->available_at)->format('Y-m-d')) . " - " . (Carbon::parse($record->available_until)->format('Y-m-d')) . ")" )
                                    ->titleColumnName('name'),
                                MorphToSelect\Type::make(User::class)
                                    ->label('User')
                                    ->titleColumnName('username'),
                            ])
                            ->hidden(function ($get) {
                                if ($get('redirect_type') == SystemNotification::REDIRECT_DYNAMIC) {
                                    return false;
                                }

                                return true;
                            })
                            ->reactive()
                            ->required()
                            ->columnSpanFull()
                            ->searchable(),

                        Fieldset::make()
                            ->schema([
                                Select::make('static_content_type')
                                    ->label('Content Type')
                                    ->options([
                                        'web' => 'Web',
                                        'text' => 'Text',
                                    ])
                                    ->reactive()
                                    ->required(),

                                TextInput::make('web_link')
                                    ->label('Web Link')
                                    ->hidden(fn (Closure $get) => $get('static_content_type') !== 'web'),
                            ])
                            ->hidden(function ($get) {
                                if ($get('redirect_type') == SystemNotification::REDIRECT_STATIC) {
                                    return false;
                                }

                                return true;
                            })
                            ->label('Static Redirect')
                            ->columns(1),

                        Fieldset::make()
                            ->schema([
                                Select::make('page_redirect')
                                    ->label('')
                                    ->options([
                                        'deal_index' => 'Deal Index',
                                        'my_referral' => 'My Referral',
                                        'notifications' => 'Notifications',
                                        'my_funbox' => 'My Funbox',
                                        'buy_giftcard' => 'Buy Giftcard',
                                    ])
                                    ->required(),
                            ])
                            ->hidden(fn ($get) => $get('redirect_type') == SystemNotification::REDIRECT_PAGE ? false : true)
                            ->label('Page Redirect')
                            ->columns(1)
                    ])
                    ->columns(2),

				Forms\Components\Card::make()
					->schema([
						Radio::make('selection_type')
							->label('Select users for sending notification method')
							->options([
								'select' => 'Select users',
								'import' => 'Import users list',
							])
							->reactive()
							->required()
							->hidden(fn (Closure $get) => $get('all_active_users') === true)
							->helperText('If want to import User list, please create notification first then import CSV in the "User" table below'),
						Select::make('users')
							->preload()
							->multiple()
							->searchable()
							->relationship('users', 'username')
                            ->options(User::pluck('username', 'id')->toArray())
							->placeholder('Enter username or select by user status')
							->hidden(fn (Closure $get) => $get('selection_type') === 'import' || $get('selection_type') === null || $get('all_active_users') === true)
							->rules([
								function (Closure $get) {
									return function (string $attribute, $value, Closure $fail) use ($get) {
										$scheduledAt = $get('scheduled_at');
										if ($scheduledAt) {
											$scheduledTime = Carbon::parse($scheduledAt);
											if (now()->diffInMinutes($scheduledTime, false) <= 30 && empty($value)) {
												$fail('The :attribute field is required when the scheduled time is within 30 minutes.');
											}
										}
									};
								}
							]),
//                        Select::make('user')
//                            ->preload()
//                            ->multiple()
//                            ->searchable()
//                            ->options(User::pluck('username', 'id')->toArray())
//                            // ->getSearchResultsUsing(fn (string $search) => User::where('username', 'like', "%{$search}%")->limit(25)->pluck('username', 'id'))
//                            ->placeholder('Enter username or select by user status')
//                            ->hidden(fn (Closure $get) => $get('all_active_users') === true)
//                            ->dehydrateStateUsing(function ($state) {
//                                    $stateData = [];
//                                    foreach ($state as $s) {
//                                        $stateData[] = intval($s);
//                                    }
//
//                                    return json_encode($stateData);
//                                })
//                            ->formatStateUsing(function ($context, $state) {
//                                if ($context == 'edit') {
//                                    $stateData = json_decode($state, true);
//                                    return $stateData;
//                                }
//                            })
//							->rules([fn($get) => $get('all_active_users') === false ? 'required' : ''])
//							->afterStateUpdated(function ($state, $record) {
//								if ($record && $state) {
//									// Convert string IDs to integers
//									$userIds = collect($state)->map(fn ($id) => (int) $id)->toArray();
//
////									// Get existing user IDs from imported records
////									$existingUserIds = $record->users()
////										->wherePivotNull('created_at')  // Only get manually added users
////										->pluck('user_id')
////										->toArray();
////
////									// Remove users that were manually added (not imported)
////									$record->users()
////										->wherePivotNull('created_at')
////										->detach();
////
////									// Attach new users with null imported_at
////									$attachData = collect($userIds)->mapWithKeys(function ($id) {
////										return [$id => ['created_at' => null]];
////									})->toArray();
////
////									$record->users()->attach($attachData);
//								}
//							}),
                        Toggle::make('all_active_users')
                            ->label('Toggle on to send notification to all active users')
                            ->reactive(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('title')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(function ($state) {
                        try {
                            $titles = [];
                            $stateData = json_decode($state, true);
                            if($stateData == null) return $state;

                            $titles = array_values($stateData);
                            return implode(', ', $titles);
                        } catch (\Exception $e) {
                            return $state;
                        };
                    }),

                // TextColumn::make('content')
                // ->formatStateUsing(function ($state) {
                //     try {
                //         $contents = [];
                //         $stateData = json_decode($state, true);
                //         if($stateData == null) return $state;

                //         $contents = array_values($stateData);
                //         return implode(', ', $contents);
                //     } catch (\Exception $e) {
                //         return $state;
                //     };
                // }),

                TextColumn::make('redirect_type')
                    ->enum(SystemNotification::REDIRECT_TYPE)
                    ->sortable(),

                TextColumn::make('content_type')
                    ->label('Dynamic Content Type')
                    ->sortable(),

                TextColumn::make('content_id')
                    ->label('Dynamic Content ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('static_content_type')
                    ->label('Static Content type')
                    ->sortable(),

                TextColumn::make('web_link')
                    ->label('Web Link'),

                TextColumn::make('scheduled_at')
                    ->label('Scheduled At')
                    ->sortable(),

                TextColumn::make('sent_at')
                    ->label('Sent At')
                    ->sortable(),

                // TextColumn::make('user')
                //     ->label('Notified User')
                //     ->formatStateUsing(function ($state) {
                //         if ($state) {
                //             $usernames = [];
                //             $stateData = json_decode($state, true);

                //             $usernames = User::whereIn('id', $stateData)->pluck('username')->toArray();
                //             return implode(', ', $usernames);
                //         }
                //     })
                //     ->wrap(),

                BadgeColumn::make('all_active_users')
                    ->label('All Active Users')
                    ->enum([
                        0 => "False",
                        1 => "True",
                    ])
                    ->colors([
                        'warning' => 0,
                        'success' => 1,
                    ]),

                TextColumn::make('created_at')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AuditsRelationManager::class,
			RelationManagers\SystemNotificationUsersRelationManager::class
		];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSystemNotifications::route('/'),
            'create' => Pages\CreateSystemNotification::route('/create'),
            'edit' => Pages\EditSystemNotification::route('/{record}/edit'),
        ];
    }
}
