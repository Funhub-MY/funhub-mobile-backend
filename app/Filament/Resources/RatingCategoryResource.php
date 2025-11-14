<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RatingCategoryResource\Pages;
use App\Filament\Resources\RatingCategoryResource\RelationManagers;
use App\Models\RatingCategory;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RatingCategoryResource extends Resource
{
    protected static ?string $model = RatingCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Merchant';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make([
                    TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->placeholder('Enter the name of the category'),

                    KeyValue::make('name_translations')
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
                                $translations = json_decode($record->name_translations ?? [], true);

                                // Map available locales to keys of KeyValue component with corresponding values
                                foreach ($locales as $locale => $language) {
                                    // Search for the key (language code) corresponding to the current language name
                                    $languageCode = array_search($language, $locales);

                                    // Set the value for the corresponding key and value in the state
                                    $set("name_translations.$language", $translations[$languageCode] ?? '');
                                }
                            } else {
                                // For other contexts or new records, map available locales to keys of KeyValue component with empty values
                                foreach ($locales as $locale => $language) {
                                    // Set the value for the corresponding key in the state
                                    $set("name_translations.$language", '');
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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->sortable(),
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
            'index' => Pages\ListRatingCategories::route('/'),
            'create' => Pages\CreateRatingCategory::route('/create'),
            'edit' => Pages\EditRatingCategory::route('/{record}/edit'),
        ];
    }
}
