<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use App\Models\Store;
use App\Models\Merchant;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\StoreResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\StoreResource\RelationManagers;
use App\Filament\Resources\StoreResource\RelationManagers\LocationRelationManager;
use Cheesegrits\FilamentGoogleMaps\Fields\Map;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static ?string $navigationIcon = 'heroicon-o-library';

    protected static ?string $navigationGroup = 'Merchant';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Store Name')
                            ->autofocus()
                            ->required()
                            ->rules('required', 'max:255'),
                        Forms\Components\Select::make('user_id')
                            ->label('Belongs To User')
                            ->preload()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => User::where('name', 'like', "%{$search}%")->limit(25))
                            ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                            ->default(fn () => User::where('id', auth()->user()->id)?->first()->id)
                            ->relationship('user','name'),

                        // categories
                        Select::make('categories')
                            ->relationship('categories', 'name')
                            ->searchable()
                            ->preload()
                            ->multiple(),

                    ]),
                Forms\Components\Section::make('Store Information')
                    ->schema([
                        Forms\Components\TextInput::make('business_phone_no')
                            ->label('Store Phone Number'),

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
                                    'address_postcode' => '%z',
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

                        Forms\Components\Textarea::make('address')
                            ->required(),
                        Forms\Components\TextInput::make('address_postcode')
                            ->required(),
                        Forms\Components\TextInput::make('lang')
                            ->helperText('This is to locate your store in the map.')
                            ->required(),
                        Forms\Components\TextInput::make('long')
                            ->helperText('This is to locate your store in the map.')
                            ->required(),
                        Forms\Components\Toggle::make('is_hq')
                            ->label('Is headquarter ?')
                            ->onIcon('heroicon-s-check-circle')
                            ->offIcon('heroicon-s-x-circle'),

                                ]),

                    Section::make('Store Business Hours')
                        ->schema([
                            Repeater::make('business_hours')
                                ->schema([
                                    Select::make('day')
                                        ->options([
                                            '1' => 'Monday',
                                            '2' => 'Tuesday',
                                            '3' => 'Wednesday',
                                            '4' => 'Thursday',
                                            '5' => 'Friday',
                                            '6' => 'Saturday',
                                            '7' => 'Sunday',
                                        ])
                                        ->required()
                                        ->label('Day')
                                        ->columnSpan('full'),
                                        Grid::make(2)
                                        ->schema([
                                            TimePicker::make('open_time')
                                                ->withoutSeconds()
                                                ->withoutDate()
                                                ->required()
                                                ->default('09:00')
                                                ->label('Open Time'),
                                            TimePicker::make('close_time')
                                                ->withoutSeconds()
                                                ->withoutDate()
                                                ->required()
                                                ->default('17:00')
                                                ->label('Close Time'),
                                        ]),
                            ])
                        ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('By User'),
                Tables\Columns\TextColumn::make('business_phone_no'),
                Tables\Columns\TextColumn::make('address'),
                Tables\Columns\TextColumn::make('address_postcode'),
                // Tables\Columns\TextColumn::make('lang'),
                // Tables\Columns\TextColumn::make('long'),
                Tables\Columns\ToggleColumn::make('is_hq')
                    ->label('Headquarter')
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
            RelationManagers\MerchantOffersRelationManager::class,
            LocationRelationManager::class,
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStores::route('/'),
            'create' => Pages\CreateStore::route('/create'),
            'edit' => Pages\EditStore::route('/{record}/edit'),
        ];
    }
}
