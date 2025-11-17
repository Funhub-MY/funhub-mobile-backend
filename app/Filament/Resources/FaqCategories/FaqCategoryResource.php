<?php

namespace App\Filament\Resources\FaqCategories;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\FaqCategories\Pages\ListFaqCategories;
use App\Filament\Resources\FaqCategories\Pages\CreateFaqCategory;
use App\Filament\Resources\FaqCategories\Pages\EditFaqCategory;
use Closure;
use Filament\Forms;
use Filament\Tables;
use App\Models\FaqCategory;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\FaqCategoryResource\Pages;
use App\Filament\Resources\FaqCategoryResource\RelationManagers;
use Filament\Forms\Components\KeyValue;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class FaqCategoryResource extends Resource
{
    protected static ?string $model = FaqCategory::class;

    protected static string | \UnitEnum | null $navigationGroup = 'Help Center';

    protected static ?int $navigationSort = 3;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make([
                    Hidden::make('user_id')
                        ->default(fn () => auth()->user()->id),

                    TextInput::make('name')
                        ->autofocus()
                        ->required(),

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
                                $translations = json_decode($record->name_translation, true);

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

                    Select::make('lang')
                        ->label('Language')
                        ->options([
                            'cn' => 'Chinese',
                            'en' => 'English',
                        ])
                        ->default('cn')
                        ->required(),

                    SpatieMediaLibraryFileUpload::make('icon')
                        ->label('Icon')
                        ->collection('icon')
                        ->columnSpan('full')
                        ->disk(function () {
                            if (config('filesystems.default') === 's3') {
                                return 's3_public';
                            }
                        })
                        ->acceptedFileTypes(['image/*'])
                        ->maxFiles(1)
                        ->rules('image'),

                    Toggle::make('is_featured')
                        ->label('Is Featured?')
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('is_featured')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        true => 'Yes',
                        false => 'No',
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        true => 'success',
                        false => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([

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
            'index' => ListFaqCategories::route('/'),
            'create' => CreateFaqCategory::route('/create'),
            'edit' => EditFaqCategory::route('/{record}/edit'),
        ];
    }
}
