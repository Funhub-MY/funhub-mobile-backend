<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RewardComponentResource\Pages;
use App\Filament\Resources\RewardComponentResource\RelationManagers;
use App\Filament\Resources\RewardComponentResource\RelationManagers\RewardsRelationManager;
use App\Models\RewardComponent;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RewardComponentResource extends Resource
{
    protected static ?string $model = RewardComponent::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Points & Rewards';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('reward')
                    ->relationship('rewards', 'name')
                    ->multiple()
                    ->required(),

                // name
                Forms\Components\TextInput::make('name')
                    ->label('Name')
                    ->autofocus()
                    ->required()
                    ->rules('required', 'max:255'),

                // description
                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->rules('required', 'max:255'),

                // user id auto fill hidden
                Forms\Components\Hidden::make('user_id')
                    ->default(fn () => auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // name
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                // description
                Tables\Columns\TextColumn::make('description')
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
            RewardsRelationManager::class,
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRewardComponents::route('/'),
            'create' => Pages\CreateRewardComponent::route('/create'),
            'edit' => Pages\EditRewardComponent::route('/{record}/edit'),
        ];
    }
}
