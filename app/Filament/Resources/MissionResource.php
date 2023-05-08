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
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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
                    Section::make('Reward')
                        ->schema([
                            TextInput::make('name')
                            ->autofocus()
                            ->required()
                            ->rules('required', 'max:255'),
            
                            TextInput::make('description'),
            
                            // event select input
                            Select::make('event')
                                ->label('Event')
                                ->options([
                                    'new_comment_added' => 'Added a Comment on an Article',
                                    'new_article_created' => 'Created a new Article',
                                    'liked_an_article' => 'Liked an Article',
                                    'liked_a_comment' => 'Liked a Comment',
                                    'claim_an_offer' => 'Claimed a Merchant Offer/Deal',
                                ])
                                ->required()
                                ->rules('required'),
                            
                            // when event selected, choose the value to meet to reward
                            TextInput::make('value')
                                ->label('Event Value')
                                ->helperText('The value to meet to reward the user, eg 1 for event new comment added')
                                ->required()
                                ->default(1)
                                ->hidden(fn ($request) => $request->input('event'))
                                ->rules('required', 'numeric', 'min:1'),
                        ]) 
                ]),
                
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
                    ->required()
                    ->rules('required', 'numeric', 'min:1')
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
                    ->sortable(),

                Tables\Columns\TextColumn::make('value')
                    ->searchable()
                    ->sortable(),

                // morph to missionable column
                Tables\Columns\TextColumn::make('missionable')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($value, $record) {
                        return $record->missionable->name;
                    }),

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
