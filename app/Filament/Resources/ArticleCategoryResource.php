<?php

namespace App\Filament\Resources;

use Closure;
use Filament\Forms;
use Filament\Tables;
use Illuminate\Support\Str;
use Filament\Resources\Form;
use Filament\Resources\Table;
use App\Models\ArticleCategory;
use Filament\Resources\Resource;
use Spatie\MediaLibrary\HasMedia;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\ArticleCategoryResource\Pages;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\ArticleCategoryResource\RelationManagers;

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

                // add parent category relationship
                Forms\Components\Select::make('parent_id')
                    ->searchable()
                    ->relationship('parent', 'name')
                    ->nullable()
                    ->columnSpan('full'),

                // is featured boolean
                Forms\Components\Toggle::make('is_featured')
                    ->label('Is Featured On Homepage?')
                    ->columnSpan('full'),

                // is active
                Forms\Components\Toggle::make('is_active')
                    ->label('Is Active?')
                    ->columnSpan('full'),

                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->unique(ArticleCategory::class, 'slug', ignoreRecord: true),


                Forms\Components\SpatieMediaLibraryFileUpload::make('icon')
                    ->label('Icon')
                    ->multiple()
                    ->collection('article_category_icon')
                    ->columnSpan('full')
                    ->disk(function () {
                        if (config('filesystems.default') === 's3') {
                            return 's3_public';
                        }
                    })
                    ->acceptedFileTypes(['image/*'])
                    ->maxFiles(1)
                    ->rules('image'),

                // Forms\Components\SpatieMediaLibraryFileUpload::make('icon')
                //     ->label('Icon')
                //     ->multiple()
                //     ->collection('article_category')
                //     ->columnSpan('full')
                //     ->disk(function () {
                //         if (config('filesystems.default') === 's3') {
                //             return 's3_public';
                //         }
                //     })
                //     ->acceptedFileTypes(['image/*'])
                //     ->maxFiles(1)
                //     ->rules('image'),

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
                // Tables\Columns\SpatieMediaLibraryImageColumn::make('image')->collection('article_category_cover')->label('Image'),

                // add parent category relationship
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),

                Tables\Columns\TextColumn::make('parent.name')->label('Parent Category')
                    ->sortable()->searchable(),

                // is_featured
                Tables\Columns\ToggleColumn::make('is_featured')->sortable()->searchable(),
                // is_active
                Tables\Columns\ToggleColumn::make('is_active')->sortable()->searchable(),
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
            AuditsRelationManager::class,
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
