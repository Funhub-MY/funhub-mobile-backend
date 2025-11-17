<?php

namespace App\Filament\Resources\ArticleCategories;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ArticleCategories\Pages\ListArticleCategories;
use App\Filament\Resources\ArticleCategories\Pages\CreateArticleCategory;
use App\Filament\Resources\ArticleCategories\Pages\EditArticleCategory;
use Closure;
use Filament\Forms;
use Filament\Tables;
use Illuminate\Support\Str;
use Filament\Tables\Table;
use App\Models\ArticleCategory;
use Filament\Resources\Resource;
use Spatie\MediaLibrary\HasMedia;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\KeyValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\ArticleCategoryResource\Pages;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\ArticleCategoryResource\RelationManagers;

class ArticleCategoryResource extends Resource
{
    protected static ?string $model = ArticleCategory::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Articles';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->label('Category Name')
                            ->helperText("This will show as default category name in admin backend system regardless of the app language set.")
                            ->required()
                            ->lazy()
                            ->afterStateUpdated(fn (string $context, $state, callable $set) => $context === 'create' ? $set('slug', Str::slug($state)) : null),

                        KeyValue::make('name_translation')
                            ->label('Category Name Translation')
                            ->keyLabel('Language')
                            ->valueLabel('Category Name Translation')
                            ->disableAddingRows()
                            ->disableDeletingRows()
                            ->disableEditingKeys()
                            ->afterStateHydrated(function ($context, $state, callable $set, $record) {
                                // Retrieve available locales
                                $locales = config('app.available_locales', []);
    
                                // If in edit context, retrieve the existing translations from the database
                                if ($context === 'edit' && $record) {
                                    // Fetch the existing translations for this record
                                    $translations = json_decode($record->name_translation ?? [], true);
    
                                    // Map available locales to keys of KeyValue component with corresponding values
                                    foreach ($locales as $locale => $language) {
                                        // Search for the key (language code) corresponding to the current language name
                                        $languageCode = array_search($language, $locales);
    
                                        // Set the value for the corresponding key and value in the state
                                        $set("name_translation.$language", $translations[$languageCode] ?? '');
                                    }
                                } else {
                                    // For other contexts or new records, map available locales to keys of KeyValue component with empty values
                                    foreach ($locales as $locale => $language) {
                                        // Set the value for the corresponding key in the state
                                        $set("name_translation.$language", '');
                                    }
                                }
                            })
                            ->dehydrateStateUsing(function ($state) {
                                // Retrieve available locales
                                $locales = config('app.available_locales', []);

                                $transformedState = [];

                                // Iterate over the keys in $state
                                foreach ($state as $key => $value) {
                                    // Search for the corresponding key in $locales
                                    $localeKey = array_search($key, $locales);

                                    // If a corresponding key is found, use it to replace the key in $state
                                    if ($localeKey !== false) {
                                        $transformedState[$localeKey] = $value;
                                    }
                                }

                                // Convert the transformed state to JSON
                                $stateJson = json_encode($transformedState);

                                return $stateJson;
                            })
                    ]),

                // add parent category relationship
                Select::make('parent_id')
                    ->searchable()
                    ->relationship('parent', 'name')
                    ->nullable()
                    ->columnSpan('full'),

                // is featured boolean
                Toggle::make('is_featured')
                    ->label('Is Featured On Homepage?')
                    ->columnSpan('full'),

                // is active
                Toggle::make('is_active')
                    ->label('Is Active?')
                    ->columnSpan('full'),

                TextInput::make('slug')
                    ->required()
                    ->unique(ArticleCategory::class, 'slug', ignoreRecord: true),


                SpatieMediaLibraryFileUpload::make('icon')
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

                RichEditor::make('description')
                    ->columnSpan('full'),
                Hidden::make('user_id')
                    ->default(fn () => auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Tables\Columns\SpatieMediaLibraryImageColumn::make('image')->collection('article_category_cover')->label('Image'),

                // add parent category relationship
                TextColumn::make('name')->sortable()->searchable(),

                TextColumn::make('parent.name')->label('Parent Category')
                    ->sortable()->searchable(),

                // is_featured
                ToggleColumn::make('is_featured')->sortable()->searchable(),
                // is_active
                ToggleColumn::make('is_active')->sortable()->searchable(),
                TextColumn::make('description')->sortable()->searchable()->html(),
                TextColumn::make('user.name')->label('Created By')
                    ->sortable()->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
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
            'index' => ListArticleCategories::route('/'),
            'create' => CreateArticleCategory::route('/create'),
            'edit' => EditArticleCategory::route('/{record}/edit'),
        ];
    }
}
