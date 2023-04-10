<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleCategoryResource\Pages;
use App\Filament\Resources\ArticleCategoryResource\RelationManagers;
use App\Models\ArticleCategory;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

class ArticleCategoryResource extends Resource
{

    protected static ?string $model = ArticleCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Articles';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->lazy()
                    ->afterStateUpdated(fn (string $context, $state, callable $set) => $context === 'create' ? $set('slug', Str::slug($state)) : null),
                // is featured boolean
                Forms\Components\Toggle::make('is_featured')
                    ->label('Is Featured On Homepage?')
                    ->columnSpan('full'),
                Forms\Components\TextInput::make('slug')
                    ->disabled()
                    ->required()
                    ->unique(ArticleCategory::class, 'slug', ignoreRecord: true),
                Forms\Components\SpatieMediaLibraryFileUpload::make('image')
                    ->collection('article_category_cover')
                    ->customProperties(['is_cover' => true])
                    ->columnSpan('full')
                    ->maxFiles(1)
                    ->rules('image'),
                Forms\Components\RichEditor::make('description')
                    ->columnSpan('full'),
                Forms\Components\Hidden::make('user_id')
                    ->default(fn () => auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('image')->collection('article_category_cover')->label('Image'),
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                // is_featured
                Tables\Columns\ToggleColumn::make('is_featured')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('description')->sortable()->searchable()->html(),
                Tables\Columns\TextColumn::make('user.name')->label('Created By')
                    ->sortable()->searchable(),
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
            'index' => Pages\ListArticleCategories::route('/'),
            'create' => Pages\CreateArticleCategory::route('/create'),
            'edit' => Pages\EditArticleCategory::route('/{record}/edit'),
        ];
    }
}
