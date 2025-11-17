<?php

namespace App\Filament\Resources\Locations;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Closure;
use Filament\Forms;
use Filament\Tables;
use App\Models\Article;
use App\Models\Location;
use App\Models\MerchantOffer;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\MorphToSelect;
use Cheesegrits\FilamentGoogleMaps\Fields\Map;
use App\Filament\Resources\Locations\Pages\ListLocations;
use App\Filament\Resources\Locations\Pages\CreateLocation;
use App\Filament\Resources\Locations\Pages\EditLocation;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\ToggleColumn;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-map';

    protected static string | \UnitEnum | null $navigationGroup = 'Locations';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('user_id')->default(fn () => auth()->id()),
                Group::make([
                    Section::make('General Information')
                        ->schema([
                            SpatieMediaLibraryFileUpload::make('gallery')
                                ->label('Cover')
                                ->collection(Location::MEDIA_COLLECTION_NAME)
                                ->columnSpan('full')
                                // disk is s3_public
                                ->disk(function () {
                                    if (config('filesystems.default') === 's3') {
                                        return 's3_public';
                                    }
                                })
                                ->acceptedFileTypes(['image/*'])
                                ->maxFiles(1)
                                ->rules('image'),

                            TextInput::make('name')
                                ->required()
                                ->placeholder('Name of Location'),

                            // boolean is_mall
                            Toggle::make('is_mall')
                                ->label('Is Mall ?')
                                ->helperText('If this location is mall, will be  used to separate mall outlets locations from Mall.')
                                ->default(false),

                            Select::make('merchant_id')
                                ->label('Attach to Merchant')
                                ->relationship('merchant', 'name')
                                ->nullable()
                                ->placeholder('Select Merchant'),

                            TextInput::make('phone_no')
                                ->placeholder('+60123456789'),

                            Select::make('status')
                                ->options([
                                    0 => 'Draft',
                                    1 => 'Published',
                                    2 => 'Archived',
                                ])
                                ->default(1),
                        ])
                ])->columnSpan(['lg' => 1]),

                Group::make([
                    Section::make('Location Details')
                        ->schema([
                            TextInput::make('auto_complete_address')
                                ->label('Find a Location')
                                ->placeholder('Start typing an address ...'),

                            Map::make('location')
                                ->autocomplete(
                                    fieldName: 'auto_complete_address',
                                    placeField: 'name',
                                    countries: ['MY'],
                                    types: ["geocode", "establishment"]
                                )
                                ->reactive()
                                ->defaultZoom(15)
                                ->defaultLocation([
                                    // klang valley coordinates
                                    'lat' => 3.1390,
                                    'lng' => 101.6869,
                                ])
                                ->reverseGeocode([
                                    'city'   => '%L',
                                    'zip'    => '%z',
                                    'state'  => '%D',
                                    'zip_code' => '%z',
                                    'address' => '%n %S',
                                ])
                                ->mapControls([
                                    'mapTypeControl'    => true,
                                    'scaleControl'      => true,
                                    'streetViewControl' => false,
                                    'rotateControl'     => true,
                                    'fullscreenControl' => true,
                                    'searchBoxControl'  => false, // creates geocomplete field inside map
                                    'zoomControl'       => false,
                                ])
                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                    // Set latitude and longitude as before
                                    $set('lat', $state['lat']);
                                    $set('lng', $state['lng']);
                                })
                                ->clickable(true),

                            TextInput::make('address')
                                ->required(),
                            TextInput::make('address_2'),

                            TextInput::make('city')
                                ->label('City (Text Address)')
                                ->required(),

                            // manually link CityLinked
                            Select::make('city_id')
                                ->label('City Linked')
                                ->relationship('cityLinked', 'name')
                                ->nullable()
                                ->searchable()
                                ->preload()
                                ->helperText('Linked Cities allow multiple names to be search against this location. Eg. KL , Kuala Lumpur etc')
                                ->placeholder('Select City to Link'),

                            TextInput::make('zip_code')
                                ->rules('numeric')
                                ->label('Postcode')
                                ->required(),
                            Select::make('state_id')
                                ->label('State')
                                ->required()
                                ->relationship('state', 'name'),
                            Select::make('country_id')
                                ->label('Country')
                                ->default(131)
                                ->required()
                                ->relationship('country', 'name'),
                            TextInput::make('google_id'),
                            Grid::make(2)
                                ->schema([
                                    TextInput::make('lat')
                                        ->columnSpan(1)
                                        ->label('Latitude'),
                                    TextInput::make('lng')
                                        ->columnSpan(1)
                                        ->label('Logitude'),
                            ]),
                        ])
                ])->columnSpan(['lg' => 1]),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => MerchantOffer::STATUS[$state] ?? $state)
                    ->color(fn ($state) => match($state) {
                        0 => 'secondary',
                        1 => 'success',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),
                ToggleColumn::make('is_mall')
                    ->label('Is Mall ?')
                    ->sortable(),

                TextColumn::make('address'),

                TextColumn::make('city')
                    ->label('City')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('cityLinked.name')
                    ->label('City Linked')
                    ->sortable()
                    ->url(fn ($record) => $record->cityLinked ? route('filament.admin.resources.cities.edit', $record->cityLinked) : null)
                    ->openUrlInNewTab()
                    ->searchable(),
                TextColumn::make('state.name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('country.name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('google_id')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('lat')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('lng')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                // filter by is_mall
                SelectFilter::make('is_mall')
                    ->label('Is Mall ?')
                    ->options([
                        '0' => 'No',
                        '1' => 'Yes',
                    ]),
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
            'index' => ListLocations::route('/'),
            'create' => CreateLocation::route('/create'),
            'edit' => EditLocation::route('/{record}/edit'),
        ];
    }
}
