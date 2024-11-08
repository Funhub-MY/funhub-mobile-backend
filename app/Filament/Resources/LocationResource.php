<?php

namespace App\Filament\Resources;

use Closure;
use Filament\Forms;
use Filament\Tables;
use App\Models\Article;
use App\Models\Location;
use Filament\Resources\Form;
use App\Models\MerchantOffer;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\MorphToSelect;
use Cheesegrits\FilamentGoogleMaps\Fields\Map;
use App\Filament\Resources\LocationResource\Pages;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\LocationResource\RelationManagers;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ToggleColumn;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Locations';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Hidden::make('user_id')->default(fn () => auth()->id()),
                Group::make([
                    Section::make('General Information')
                        ->schema([
                            Forms\Components\SpatieMediaLibraryFileUpload::make('gallery')
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

                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->placeholder('Name of Location'),

                            // boolean is_mall
                            Forms\Components\Toggle::make('is_mall')
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
                                ->clickable(true),

                            Forms\Components\TextInput::make('address')
                                ->required(),
                            Forms\Components\TextInput::make('address_2'),

                            Forms\Components\TextInput::make('city')
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

                            Forms\Components\TextInput::make('zip_code')
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
                    ->url(fn ($record) => route('filament.resources.cities.edit', $record->cityLinked))
                    ->openUrlInNewTab()
                    ->searchable(),


                TextColumn::make('state.name'),

                TextColumn::make('country.name')
            ])
            ->filters([
                // filter by is_mall
                Tables\Filters\SelectFilter::make('is_mall')
                    ->label('Is Mall ?')
                    ->options([
                        '0' => 'No',
                        '1' => 'Yes',
                    ]),
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
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}
