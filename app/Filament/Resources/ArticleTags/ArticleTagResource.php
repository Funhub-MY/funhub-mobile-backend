<?php

namespace App\Filament\Resources\ArticleTags;

use App\Filament\Resources\ArticleTags\ArticleTagResource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use App\Filament\Resources\ArticleTags\Pages\ListArticleTags;
use App\Filament\Resources\ArticleTags\Pages\CreateArticleTag;
use App\Filament\Resources\ArticleTags\Pages\ViewArticleTags;
use App\Filament\Resources\ArticleTags\Pages\EditArticleTag;
use Filament\Forms;
use Filament\Tables;
use App\Models\ArticleTag;
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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Articles';

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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                // sum articles count
                TextColumn::make('articles_count')
                    ->label('Articles Count')
                    ->sortable(),

                // Tables\Columns\TextColumn::make('user.name')->label('Created By')
                //     ->sortable()->searchable(),
            ])
            ->defaultSort('articles_count', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('View')
                ->label('View Articles')
                ->action(function ($record) {
                    return redirect(ArticleTagResource::getUrl('view', ['record' => $record->id]));
                })
                ->icon('heroicon-s-eye'),
            ])
            ->toolbarActions([
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
            'index' => ListArticleTags::route('/'),
            'create' => CreateArticleTag::route('/create'),
            'view' => ViewArticleTags::route('/{record}/view'),
            'edit' => EditArticleTag::route('/{record}/edit'),
        ];
    }
}
