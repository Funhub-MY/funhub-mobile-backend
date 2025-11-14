<?php

namespace App\Filament\Resources\ArticleResource\RelationManagers;

use App\Models\Article;
use App\Models\Interaction;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InteractionsRelationManager extends RelationManager
{
    protected static string $relationship = 'interactions';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('status')
                    ->options(Interaction::STATUS)
                    ->default(0)
                    ->required(),
                Forms\Components\Select::make('type')
                    ->options([
                        1 => 'Like',
                        2 => 'Dislike',
                        3 => 'Share',
                        4 => 'Bookmark',
                    ])
                    ->default(0)
                    ->required(),
                Forms\Components\Select::make('user_id')
                    ->label('User')
                    ->preload()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => User::where('name', 'like', "%{$search}%")->limit(25))
                    ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                    ->default(fn () => User::where('id', auth()->user()->id)?->first()->id)
                    ->relationship('user','name'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('By User'),
                Tables\Columns\TextColumn::make('type')
                    ->enum([
                        1 => 'Like',
                        2 => 'Dislike',
                        3 => 'Share',
                        4 => 'Bookmark',
                    ]),
                Tables\Columns\TextColumn::make('meta'),
                Tables\Columns\BadgeColumn::make('status')
                    ->enum([
                        0 => 'Draft',
                        1 => 'Published',
                        2 => 'Hidden',
                    ])
                    ->colors([
                        'primary' => static fn ($state): bool => $state === 0,
                        'success' => static fn ($state): bool => $state === 1,
                        'danger' => static fn ($state): bool => $state === 2,
                    ]),
                Tables\Columns\TextColumn::make('created_at'),
                Tables\Columns\TextColumn::make('updated_at'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
