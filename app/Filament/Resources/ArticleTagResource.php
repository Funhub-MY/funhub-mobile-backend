<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\ArticleTag;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\ArticleTagResource\Pages;
use App\Filament\Resources\ArticleTagResource\RelationManagers;
use App\Models\ArticleTagsArticlesCount;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use Illuminate\Database\Eloquent\Model;

class ArticleTagResource extends Resource
{
    protected static ?string $model = ArticleTagsArticlesCount::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Articles';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Article Tags';

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Forms\Components\Hidden::make('user_id')
                //     ->default(fn () => auth()->id()),
                // Forms\Components\TextInput::make('name')
                //     ->required()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                // sum articles count
                Tables\Columns\TextColumn::make('articles_count')
                    ->label('Articles Count')
                    ->sortable(),

                // Tables\Columns\TextColumn::make('user.name')->label('Created By')
                //     ->sortable()->searchable(),
            ])
            ->defaultSort('articles_count', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('View')
                ->label('View Articles')
                ->action(function ($record) {
                    return redirect(ArticleTagResource::getUrl('view', ['record' => $record->id]));
                })
                ->icon('heroicon-s-eye'),
            ])
            ->bulkActions([
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticleTags::route('/'),
            'create' => Pages\CreateArticleTag::route('/create'),
            'view' => Pages\ViewArticleTags::route('/{record}/view'),
            'edit' => Pages\EditArticleTag::route('/{record}/edit'),
        ];
    }
}
