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
                            ->autofocus()
                            ->required()
                            ->rules('required', 'max:255'),

                            Textarea::make('description')
                                ->required(),

                          // media gallery
                            Forms\Components\SpatieMediaLibraryFileUpload::make('gallery')
                               ->label('Images')
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

                        ]),
                    Section::make('Reward Details')
                        ->schema([
                            // reward type
                            Forms\Components\MorphToSelect::make('missionable')
                            ->required()
                            ->label('Reward Type')
                            ->types([
                                Forms\Components\MorphToSelect\Type::make(Reward::class)
                                ->titleColumnName('name'),
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
                                    'daily' => 'Daily at Midnight',
                                    'monthly' => 'Monthly at Start of Month',
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
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('frequency')
                    ->searchable()
                    ->sortable(),

                // morph to missionable column
                Tables\Columns\TextColumn::make('missionable.name')
                    ->label('Rewards')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reward_quantity')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reward_limit')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('auto_disburse_rewards')
                    ->label('Auto Disburse Rewards')
                    ->enum([
                        false => 'No',
                        true => 'Yes',
                    ])
                    ->colors([
                        'secondary' => false,
                        'success' => true,
                    ])
                    ->sortable(),


                Tables\Columns\BadgeColumn::make('status')
                    ->enum([
                        0 => 'Disabled',
                        1 => 'Enabled',
                    ])
                    ->colors([
                        'secondary' => 0,
                        'success' => 1,
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->searchable()
                    ->sortable(),
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
