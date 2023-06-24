<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MissionResource\Pages;
use App\Filament\Resources\MissionResource\RelationManagers;
use App\Models\Mission;
use App\Models\Reward;
use App\Models\RewardComponent;
use Filament\Forms;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Closure;
use Filament\Forms\Components\Textarea;

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
            
                            // event select input
                            Select::make('event')
                                ->label('Event')
                                ->options(config('app.event_matrix'))
                                ->required()
                                ->rules('required'),
                            
                            // when event selected, choose the value to meet to reward
                            TextInput::make('value')
                                ->label('When Event Value is Met')
                                ->helperText('The value to meet to reward the user, eg 1 for event new comment added')
                                ->required()
                                ->default(1)
                                ->rules('required', 'numeric', 'min:1'),

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
                        ]) 
                ]),
                
               Group::make()
                ->schema([
                    Section::make('Reward Details')
                        ->schema([
                            // reward type
                            Forms\Components\MorphToSelect::make('missionable')
                            ->label('Reward Type')
                            ->types([
                                Forms\Components\MorphToSelect\Type::make(Reward::class)->titleColumnName('name'),
                                Forms\Components\MorphToSelect\Type::make(RewardComponent::class)->titleColumnName('name'),
                            ]),

                            // how many to reward
                            TextInput::make('reward_quantity')
                                ->label('Reward Quantity')
                                ->helperText('How many to reward the user')
                                ->required()
                                ->rules('required', 'numeric', 'min:1')
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

                Tables\Columns\TextColumn::make('event')
                    ->searchable()
                    ->formatStateUsing(fn (string $state): string => config('app.event_matrix')[$state] ?? $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('value')
                    ->label('Criteria Value')
                    ->searchable()
                    ->sortable(),

                // morph to missionable column
                Tables\Columns\TextColumn::make('missionable.name')
                    ->label('Rewards')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reward_quantity')
                    ->searchable()
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
            //
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
