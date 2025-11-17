<?php

namespace App\Filament\Resources\PromotionCodes\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('usage_count')
                    ->numeric()
                    ->minValue(0)
                    ->label('Usage Count'),
                DateTimePicker::make('last_used_at')
                    ->label('Last Used At'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
					->searchable(query: function (Builder $query, string $search): Builder {
						return $query->where('users.id', 'like', "%{$search}%");
					}),
                TextColumn::make('name')
                    ->sortable()
					->searchable(query: function (Builder $query, string $search): Builder {
						return $query->where('users.name', 'like', "%{$search}%");
					}),
                TextColumn::make('username')
                    ->sortable()
					->searchable(query: function (Builder $query, string $search): Builder {
						return $query->where('users.username', 'like', "%{$search}%");
					}),
                TextColumn::make('email')
                    ->sortable()
					->searchable(query: function (Builder $query, string $search): Builder {
						return $query->where('users.email', 'like', "%{$search}%");
					}),
                TextColumn::make('pivot.usage_count')
                    ->label('Usage Count')
                    ->sortable(),
                TextColumn::make('pivot.last_used_at')
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
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
