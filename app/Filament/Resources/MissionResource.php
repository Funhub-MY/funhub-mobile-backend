<?php

namespace App\Filament\Resources;

use Closure;
use Filament\Forms;
use Filament\Tables;
use App\Models\Reward;
use App\Models\Mission;
use Filament\Resources\Form;
use Filament\Resources\Table;
use App\Models\RewardComponent;
use Filament\Resources\Resource;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\MorphToSelect;
use App\Filament\Resources\MissionResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\MissionResource\RelationManagers;
use App\Filament\Resources\MissionResource\RelationManagers\ParticipantsRelationManager;
use Filament\Forms\Components\KeyValue;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class MissionResource extends Resource
{
    protected static ?string $model = Mission::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-check';

    protected static ?string $navigationGroup = 'Points & Rewards';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
               Group::make()
                ->schema([
                    Section::make('Missions Details')
                        ->schema([
                            TextInput::make('name')
                                ->label('Default Name')
                                ->autofocus()
                                ->required()
                                ->rules('required', 'max:255'),

                            KeyValue::make('name_translation')
                                ->label('Name Translation')
                                ->keyLabel('Language Code (e.g., en, zh)')
                                ->valueLabel('Translation')
                                ->disableAddingRows()
                                ->disableDeletingRows()
                                ->disableEditingKeys()
                                ->afterStateHydrated(function ($context, $state, callable $set, $record) {
                                    // Retrieve available locales
                                    $locales = config('app.available_locales', []);

                                    // If in edit context, retrieve the existing translations from the database
                                    if ($context === 'edit' && $record) {
                                        if (!isset($record->name_translation)) {
                                            $record->name_translation = [];
                                        } else {
                                            $translations = json_decode($record->name_translation ?? [], true);
                                        }

                                        // Map available locales to keys of KeyValue component with corresponding values
                                        foreach ($locales as $locale => $language) {
                                            // Search for the key (language code) corresponding to the current language name
                                            $languageCode = array_search($language, $locales);

                                            // Set the value for the corresponding key and value in the state
                                            $set("name_translation.$language", $translations[$languageCode] ?? '');
                                        }
                                    } else {
                                        // For other contexts or new records, map available locales to keys of KeyValue component with empty values
                                        foreach ($locales as $locale => $language) {
                                            // Set the value for the corresponding key in the state
                                            $set("name_translation.$language", '');
                                        }
                                    }
                                })
                                ->dehydrateStateUsing(function ($state) {
                                    // Retrieve available locales
                                    $locales = config('app.available_locales', []);

                                    $transformedState = [];

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

                            Select::make('predecessors')
                                ->multiple()
                                ->relationship('predecessors', 'name')
                                ->preload()
                                ->label('Required Missions')
                                ->helperText('Select one-off missions that must be completed before this mission can be started')
                                ->options(function () {
                                    return Mission::where('frequency', 'one-off')->pluck('name', 'id');
                                }),

                            Textarea::make('description')
                                ->required(),

                            KeyValue::make('description_translation')
                                ->label('Description Translation')
                                ->keyLabel('Language Code (e.g., en, zh)')
                                ->valueLabel('Translation')
                                ->disableAddingRows()
                                ->disableDeletingRows()
                                ->disableEditingKeys()
                                ->afterStateHydrated(function ($context, $state, callable $set, $record) {
                                    // Retrieve available locales
                                    $locales = config('app.available_locales', []);

                                    // If in edit context, retrieve the existing translations from the database
                                    if ($context === 'edit' && $record) {
                                        // Fetch the existing translations for this record
                                        if (!isset($record->description_translation)) {
                                            $record->description_translation = [];
                                        } else {
                                            $translations = json_decode($record->description_translation ?? [], true);
                                        }
                                        foreach ($locales as $locale => $language) {
                                            // Search for the key (language code) corresponding to the current language name
                                            $languageCode = array_search($language, $locales);

                                            // Set the value for the corresponding key and value in the state
                                            $set("description_translation.$language", $translations[$languageCode] ?? '');
                                        }
                                    } else {
                                        // For other contexts or new records, map available locales to keys of KeyValue component with empty values
                                        foreach ($locales as $locale => $language) {
                                            // Set the value for the corresponding key in the state
                                            $set("description_translation.$language", '');
                                        }
                                    }
                                })
                                ->dehydrateStateUsing(function ($state) {
                                    // Retrieve available locales
                                    $locales = config('app.available_locales', []);

                                    $transformedState = [];
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


                          // media gallery
                            Forms\Components\SpatieMediaLibraryFileUpload::make('gallery')
                               ->label('Reward Image')
                               ->multiple()
                               ->collection(Mission::MEDIA_COLLECTION_NAME)
                               ->columnSpan('full')
                               // disk is s3_public
                               ->disk(function () {
                                   if (config('filesystems.default') === 's3') {
                                       return 's3_public';
                                   }
                               })
                               ->acceptedFileTypes(['image/*'])
                               ->maxFiles(20)
                               ->rules('image'),
                            Forms\Components\SpatieMediaLibraryFileUpload::make('mission_completed_image_en')
                               ->label('Completed Mission Image (Engish)')
                               ->collection(Mission::COMPLETED_MISSION_COLLECTION_EN)
                               ->columnSpan('full')
                               // disk is s3_public
                               ->disk(function () {
                                   if (config('filesystems.default') === 's3') {
                                       return 's3_public';
                                   }
                               })
                               ->acceptedFileTypes(['image/*'])
                               ->maxFiles(20)
                               ->rules('image'),
                            Forms\Components\SpatieMediaLibraryFileUpload::make('mission_completed_image_zh')
                               ->label('Completed Mission Image (Chinese)')
                               ->collection(Mission::COMPLETED_MISSION_COLLECTION_ZH)
                               ->columnSpan('full')
                               // disk is s3_public
                               ->disk(function () {
                                   if (config('filesystems.default') === 's3') {
                                       return 's3_public';
                                   }
                               })
                               ->acceptedFileTypes(['image/*'])
                               ->maxFiles(20)
                               ->rules('image'),
                    ]),
                    Section::make('Mission Goals')
                            ->schema([
                                 // events and values repeater
                                Forms\Components\Repeater::make('events_values')
                                ->label('Events and Values')
                                ->schema([
                                    Select::make('event')
                                        ->label('Event')
                                        ->options(config('app.event_matrix'))
                                        ->required()
                                        ->rules('required'),
                                    TextInput::make('value')
                                        ->label('Value')
                                        ->required()
                                        ->rules('required', 'numeric', 'min:1'),
                                ])
                                ->columns(2)
                                ->required()
                                ->rules('required'),
                            ])
                ]),

               Group::make()
                ->schema([
                    Section::make('Status')
                        ->schema([

                            // status 0 is disabled, 1 is enabled, default enabled
                            Select::make('status')
                                ->label('Status')
                                ->options([
                                    0 => 'Disabled',
                                    1 => 'Enabled',
                                ])
                                ->default(1)
                                ->required()
                                ->rules('required'),

                            // enabled_at datetime
                            Forms\Components\DatePicker::make('enabled_at')
                                ->label('Enabled At')
                                ->nullable()
                                ->helperText('If you choose a future date, mission only enabled at that point'),

                            Forms\Components\Toggle::make('disable_fcm')
                                ->label('Disable FCM')
                                ->onIcon('heroicon-s-check-circle')
                                ->offIcon('heroicon-s-x-circle'),

                        ]),
                    Section::make('Reward Details')
                        ->schema([
                            // reward type
                            Forms\Components\MorphToSelect::make('missionable')
                            ->required()
                            ->label('Reward Type')
                            ->types([
                                Forms\Components\MorphToSelect\Type::make(Reward::class)
                                    ->titleColumnName('name')
                                    ->modifyOptionsQueryUsing(function ($query) {
                                        return $query->select('rewards.id', 'rewards.name')
                                            ->whereIn('id', function($subquery) {
                                                $subquery->selectRaw('MIN(id)')
                                                    ->from('rewards')
                                                    ->groupBy('name');
                                            });
                                    }),
                                Forms\Components\MorphToSelect\Type::make(RewardComponent::class)
                                ->titleColumnName('name'),
                            ]),

                            // how many to reward
                            TextInput::make('reward_quantity')
                                ->label('Reward Quantity')
                                ->helperText('How many to reward the user')
                                ->required()
                                ->rules('required', 'numeric', 'min:1'),

                            // reward limit
                            TextInput::make('reward_limit')
                                ->label('Max Reward Limit')
                                ->helperText('How many reward to be given to user, once hit limit, mission will no longer reward user. Leave empty if no limit set.'),

                            // frequency select input
                            Select::make('frequency')
                                ->label('Frequency')
                                ->options([
                                    'one-off' => 'One-off (Non Repeatable)',
                                    'accumulated' => 'Accumulated (Repeatable)',
                                    'daily' => 'Daily, Resets Midnight (Repeatable)',
                                    'monthly' => 'Monthly, Resets Start of Month (Repeatable)',
                                ])
                                ->helperText('This determins how often mission is checked with user current scores to determine whether to disburse reward or not.')
                                ->default('one-off')
                                ->required()
                                ->rules('required'),

                            // auto_disburse_rewards
                            Forms\Components\Toggle::make('auto_disburse_rewards')
                                ->label('Auto Disburse Rewards')
                                ->default(true)
                                ->helperText('If enabled, mission will automatically disburse rewards based on frequency and reward limit. if not, user has to self to claimable missions to claim.')
                                ->columnSpan('full'),
                        ])
                ]),

            // user id auto fill hidden
                Forms\Components\Hidden::make('user_id')
                ->default(fn () => auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('frequency')
                    ->searchable(),

                // morph to missionable column
                Tables\Columns\TextColumn::make('missionable.name')
                    ->label('Rewards')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reward_quantity'),

                Tables\Columns\TextColumn::make('reward_limit'),

                Tables\Columns\BadgeColumn::make('auto_disburse_rewards')
                    ->label('Auto Disburse Rewards')
                    ->enum([
                        false => 'No',
                        true => 'Yes',
                    ])
                    ->colors([
                        'secondary' => false,
                        'success' => true,
                    ]),


                Tables\Columns\BadgeColumn::make('status')
                    ->enum([
                        0 => 'Disabled',
                        1 => 'Enabled',
                    ])
                    ->colors([
                        'secondary' => 0,
                        'success' => 1,
                    ]),

                Tables\Columns\ToggleColumn::make('disable_fcm')
                    ->label('Disable FCM'),

                Tables\Columns\TextColumn::make('created_at')
                    ->searchable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->searchable(),
                Tables\Columns\TextColumn::make('participants_count')
                    ->label('Total Participants')
                    ->counts('participants'),
            ])
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
            ParticipantsRelationManager::class,
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMissions::route('/'),
            'create' => Pages\CreateMission::route('/create'),
            'edit' => Pages\EditMission::route('/{record}/edit'),
        ];
    }
}
