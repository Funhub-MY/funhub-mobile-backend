<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Illuminate\Support\Str;
use Filament\Resources\Form;
use Filament\Resources\Table;
use App\Models\MerchantCategory;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\MerchantCategoryResource\Pages;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\MerchantCategoryResource\RelationManagers;
use Filament\Forms\Components\KeyValue;

class MerchantCategoryResource extends Resource
{
    protected static ?string $model = MerchantCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Merchant';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // parent category
                Forms\Components\Select::make('parent_id')
                    ->label('Parent Category')
                    ->nullable()
                    ->preload()
                    ->searchable()
                    ->relationship('parent', 'name'),

                Forms\Components\TextInput::make('name')
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
                            if (!isset($record->name_translation)) {
                                $record->name_translation = [];
                            } else {
                                $translations = json_decode($record->name_translation ?? [], true);
                            }

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
                    }),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->unique(MerchantCategory::class, 'slug', ignoreRecord: true),
                Forms\Components\SpatieMediaLibraryFileUpload::make('image')
                    ->collection('merchant_category_cover')
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
                  // Tables\Columns\SpatieMediaLibraryImageColumn::make('image')->collection('article_category_cover')->label('Image'),
                  Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                  Tables\Columns\TextColumn::make('parent.name')->sortable()->searchable(),
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
            'index' => Pages\ListMerchantCategories::route('/'),
            'create' => Pages\CreateMerchantCategory::route('/create'),
            'edit' => Pages\EditMerchantCategory::route('/{record}/edit'),
        ];
    }
}
