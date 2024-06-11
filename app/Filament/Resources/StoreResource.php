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
use App\Models\Location;
use Cheesegrits\FilamentGoogleMaps\Fields\Geocomplete;
use Cheesegrits\FilamentGoogleMaps\Fields\Map;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
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
                            ->helperText('Will be also used as Location name, if new location is added. eg. KFC Midvalley')
                            ->required()
                            ->rules('required', 'max:255'),
                        Forms\Components\Select::make('user_id')
                            ->label('Linked User Account')
                            ->preload()
                            ->searchable()
                            ->helperText('User account that has merchant attached to it. A store must share same linked user account as merchant to appear under Merchant > Stores')
                            ->getSearchResultsUsing(fn (string $search) => User::where('name', 'like', "%{$search}%")->limit(25))
                            ->getOptionLabelUsing(fn ($value): ?string => 'ID:' . User::find($value)?->id. ' ' .User::find($value)?->name)
                            ->default(fn () => User::where('id', auth()->user()->id)?->first()->id)
                            ->relationship('user','name'),

                        Toggle::make('use_store_redeem')
                            ->label('Use Store Redeem Code Instead')
                            ->onIcon('heroicon-s-check-circle')
                            ->offIcon('heroicon-s-x-circle')
                            ->reactive()
                            ->default(false),

                        TextInput::make('redeem_code')
                            ->label('Cashier Redeem Code (6 Digit)')
                            ->visible(fn ($get) => $get('use_store_redeem') === true)
                            ->disabled(fn ($livewire) => $livewire instanceof CreateRecord)
                            ->rules('digits:6')
                            ->disabled()
                            ->numeric()
                            ->nullable()
                            ->unique(Merchant::class, 'redeem_code', ignoreRecord: true)
                            ->helperText('Auto-generated, used when cashier validates merchant offers, will be provided to user during offer redemption in store'),

                        // categories
                        Select::make('categories')
                            ->relationship('categories', 'name')
                            ->searchable()
                            ->preload()
                            ->multiple(),

                    ]),
                    Forms\Components\Section::make('Location Information')
                                ->schema([
                                    Forms\Components\TextInput::make('business_phone_no')
                                        ->label('Store Phone Number'),

                                    // select from existing location or manual enter
                                    Radio::make('location_type')
                                        ->options([
                                            'existing' => 'Choose from existing location (articles tagged before)',
                                            'manual' => 'Enter new location',
                                        ])
                                        ->default('existing')
                                        ->reactive()
                                        ->required(),

                                    // location choose from a location to attach
                                    Select::make('location_id')
                                        ->label('Location')
                                        ->helperText('Choose from existing location (Locations tagged before)')
                                        ->getSearchResultsUsing(fn (string $search) => Location::where('name', 'like', "%{$search}%")
                                            ->orWhere('address', 'like', "%{$search}%")
                                            ->limit(25)
                                            ->get()
                                            ->mapWithKeys(function ($location) {
                                                return [$location->id => $location->name . ' - ' . $location->full_address];
                                            })
                                        )
                                        ->hidden(fn ($get) => $get('location_type') === 'manual')
                                        ->getOptionLabelUsing(fn ($value): ?string => Location::find($value)?->name . ' - ' . Location::find($value)?->full_address)
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, $set) {
                                            if ($state) {
                                                $location = Location::find($state);
                                                if ($location) {
                                                    $set('address', $location->address);
                                                    $set('address_postcode', $location->zip_code);
                                                    $set('lang', $location->lat);
                                                    $set('long', $location->lng);
                                                    $set('state_id', $location->state_id);
                                                    $set('country_id', $location->country_id);
                                                }
                                            } else {
                                                $set('address', '');
                                                $set('address_postcode', '');
                                                $set('lang', '');
                                                $set('long', '');
                                                $set('state_id', '');
                                                $set('country_id', '');
                                            }
                                        })
                                        ->searchable(),

                                    Forms\Components\Textarea::make('address')
                                        ->disabled(fn ($get) => $get('location_type') === 'existing')
                                        ->required(),
                                    Forms\Components\TextInput::make('address_postcode')
                                    ->disabled(fn ($get) => $get('location_type') === 'existing')
                                        ->required(),


                                    Select::make('state_id')
                                        ->label('State')
                                        ->preload()
                                        ->searchable()
                                        ->relationship('state', 'name')
                                        ->required(),

                                    Select::make('country_id')
                                        ->label('Country')
                                        ->preload()
                                        ->searchable()
                                        ->relationship('country', 'name')
                                        ->required(),

                                    Forms\Components\TextInput::make('lang')
                                        ->label('Latitude')
                                        ->helperText('This is to locate your store in the map. Leave 0 if not sure')
                                        ->disabled(fn ($get) => $get('location_type') === 'existing'),
                                    Forms\Components\TextInput::make('long')
                                        ->label('Logitude')
                                        ->helperText('This is to locate your store in the map. Leave 0 if not sure')
                                        ->disabled(fn ($get) => $get('location_type') === 'existing'),

                                    Forms\Components\Toggle::make('is_hq')
                                        ->label('Is headquarter ?')
                                        ->onIcon('heroicon-s-check-circle')
                                        ->offIcon('heroicon-s-x-circle'),

                                    SpatieMediaLibraryFileUpload::make('company_photos')
                                        ->label('Store Photos')
                                        ->multiple()
                                        ->maxFiles(7)
                                        ->collection(Store::MEDIA_COLLECTION_PHOTOS)
                                        ->required()
                                        ->columnSpan('full')
                                        ->disk(function () {
                                            if (config('filesystems.default') === 's3') {
                                                return 's3_public';
                                            }
                                        })
                                        ->acceptedFileTypes(['image/*'])
                                        ->rules('image'),
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
                    ->label('Linked User Account'),
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
