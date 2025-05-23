<?php

namespace App\Filament\Resources\PromotionCodeResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('usage_count')
                    ->numeric()
                    ->minValue(0)
                    ->label('Usage Count'),
                Forms\Components\DateTimePicker::make('last_used_at')
                    ->label('Last Used At'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
					->searchable(query: function (Builder $query, string $search): Builder {
						return $query->where('users.id', 'like', "%{$search}%");
					}),
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
					->searchable(query: function (Builder $query, string $search): Builder {
						return $query->where('users.name', 'like', "%{$search}%");
					}),
                Tables\Columns\TextColumn::make('username')
                    ->sortable()
					->searchable(query: function (Builder $query, string $search): Builder {
						return $query->where('users.username', 'like', "%{$search}%");
					}),
                Tables\Columns\TextColumn::make('email')
                    ->sortable()
					->searchable(query: function (Builder $query, string $search): Builder {
						return $query->where('users.email', 'like', "%{$search}%");
					}),
                Tables\Columns\TextColumn::make('pivot.usage_count')
                    ->label('Usage Count')
                    ->sortable(),
                Tables\Columns\TextColumn::make('pivot.last_used_at')
                    ->label('Last Used')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Disable create action as users should claim codes through the app
                // Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
