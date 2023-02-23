<?php

namespace App\Filament\Resources\ArticleResource\RelationManagers;

use App\Models\Article;
use App\Models\Comment;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('body')
                    ->label('Comment'),
                Forms\Components\Select::make('status')
                    ->options(Comment::STATUS)
                    ->default(0)
                    ->required(),
                Forms\Components\Select::make('user_id')
                    ->label('Belongs To User')
                    ->preload()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => User::where('name', 'like', "%{$search}%")->limit(25))
                    ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                    ->default(fn () => User::where('id', auth()->user()->id)?->first()->id)
                    ->relationship('user','name'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('By User'),
                Tables\Columns\TextColumn::make('body')
                    ->label('Comments')
                    ->words(40)
                    ->wrap(),
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
