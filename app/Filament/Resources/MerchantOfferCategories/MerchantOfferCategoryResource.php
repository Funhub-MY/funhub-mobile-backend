<?php

namespace App\Filament\Resources\MerchantOfferCategories;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\MerchantOfferCategories\Pages\ListMerchantOfferCategories;
use App\Filament\Resources\MerchantOfferCategories\Pages\CreateMerchantOfferCategory;
use App\Filament\Resources\MerchantOfferCategories\Pages\EditMerchantOfferCategory;
use Filament\Forms;
use Filament\Tables;
use Illuminate\Support\Str;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use App\Models\MerchantOfferCategory;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\KeyValue;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\MerchantOfferCategoryResource\Pages;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\MerchantOfferCategoryResource\RelationManagers;

class MerchantOfferCategoryResource extends Resource
{
    protected static ?string $model = MerchantOfferCategory::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Merchant Offers';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make([
                    TextInput::make('name')
                        ->label('Category Name')
                        ->helperText("This will show as default category name in admin backend system regardless of the app language set.")
                        ->autofocus()
                        ->required()
                        ->lazy()
                        ->afterStateUpdated(fn (string $context, $state, callable $set) => $context === 'create' ? $set('slug', Str::slug($state)) : null)
                        ->columnSpanFull(),

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
                        ->columnSpanFull(),

                    RichEditor::make('description')
                        ->columnSpanFull(),

                    TextInput::make('slug')
                        ->required()
                        ->unique(MerchantOfferCategory::class, 'slug', ignoreRecord: true)
                        ->helperText('Must not have space, replace space with dash. eg. food-and-beverage')
                        ->columnSpanFull(),

                    Select::make('parent_id')
                        ->label('Parent Category')
                        ->relationship('parent', 'name')
                        ->preload()
                        ->nullable(),

                    Group::make()
                        ->schema([
                            Toggle::make('is_featured')
                                ->label('Is Featured?')
                                ->helperText('This will inserted next to "All" category in the app.'),

                            Toggle::make('is_active')
                                ->label('Is Active?'),
                        ]),

                    SpatieMediaLibraryFileUpload::make('icon')
                        ->label('Icon')
                        ->collection('merchant_offer_category')
                        ->disk(function () {
                            if (config('filesystems.default') === 's3') {
                                return 's3_public';
                            }
                        })
                        ->acceptedFileTypes(['image/*'])
                        ->rules('image')
                        ->columnSpanFull(),

                    Hidden::make('user_id')
                        ->default(fn () => auth()->id()),
                ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),

                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('parent.name')
                    ->label('Parent Category')
                    ->sortable()
                    ->searchable(),

                ToggleColumn::make('is_featured')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->sortable(),

                TextColumn::make('description')
                    ->html(),

                TextColumn::make('user.name')
                    ->label('Created By')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->sortable(),
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
            'index' => ListMerchantOfferCategories::route('/'),
            'create' => CreateMerchantOfferCategory::route('/create'),
            'edit' => EditMerchantOfferCategory::route('/{record}/edit'),
        ];
    }
}
