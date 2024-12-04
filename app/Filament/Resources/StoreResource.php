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
use App\Models\MerchantCategory;
use Awcodes\FilamentTableRepeater\Components\TableRepeater;
use Cheesegrits\FilamentGoogleMaps\Fields\Geocomplete;
use Cheesegrits\FilamentGoogleMaps\Fields\Map;
use Filament\Forms\Components\FileUpload;
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
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Google\Service\Compute\Tags;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static ?string $navigationIcon = 'heroicon-o-library';

    protected static ?string $navigationGroup = 'Merchant';

    protected static ?int $navigationSort = 3;

    protected static function getNavigationBadge(): ?string
    {
        $unlistedStores = Store::where('status', Store::STATUS_INACTIVE)->count();

        return ($unlistedStores > 0) ? (string) $unlistedStores : null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Group::make()
                    ->columnSpanFull()
                    ->columns([
                        'xs' => 1,
                        'lg' => 2,
                        'md' => 2,
                    ])
                    ->schema([
                        Forms\Components\Section::make('Basic Information')
                        ->columnSpan(1)
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Store Name')
                                ->autofocus()
                                ->helperText('Will be also used as Location name, if new location is added. eg. KFC Midvalley')
                                ->required()
                                ->rules('required', 'max:255'),
							TextInput::make('slug')
								->maxLength(255)
								->required()
								->default(Str::random(10))
//								->disabled()
								->unique(Store::class, 'slug', ignoreRecord: true),
							Forms\Components\Select::make('user_id')
                                ->label('Linked User Account')
                                ->searchable()
                                ->getOptionLabelFromRecordUsing(fn($record) => $record->name . ($record->username ? ' (username: ' . $record->username  . ')' : ''))
                                ->helperText('User account that has merchant attached to it. A store must share same linked user account as merchant to appear under Merchant > Stores')
                                ->relationship('user', 'name'),

                            Forms\Components\Select::make('merchant_id')
                                ->label('Linked Merchant')
                                ->searchable()
                                ->relationship('merchant', 'name'),

                            Toggle::make('use_store_redeem')
                                ->label('Use Store Redeem Code Instead')
                                ->onIcon('heroicon-s-check-circle')
                                ->offIcon('heroicon-s-x-circle')
                                ->reactive()
                                ->default(false),

                            TextInput::make('redeem_code')
                                ->label('Cashier Redeem Code (6 Digit)')
                                ->visible(fn($get) => $get('use_store_redeem') === true)
                                ->disabled(fn($livewire) => $livewire instanceof CreateRecord)
                                ->rules('digits:6')
                                ->disabled()
                                ->numeric()
                                ->nullable()
                                ->unique(Store::class, 'redeem_code', ignoreRecord: true)
                                ->helperText('Auto-generated, used when cashier validates merchant offers, will be provided to user during offer redemption in store'),

                            // categories
                            Select::make('categories')
                                ->relationship('categories', 'name')
                                ->getOptionLabelFromRecordUsing(fn($record) => $record->name . ($record->parent ? ' - ' . $record->parent->name : ''))
                                ->searchable()
                                ->preload()
                                ->multiple(),

                        ]),
                        Forms\Components\Section::make('Operation')
                        ->columnSpan(1)
                        ->schema([
                            Forms\Components\Select::make('status')
                                ->options(Store::STATUS)
                                ->default(Store::STATUS_ACTIVE)
                                ->helperText('Unlisted store will not show up in App')
                                ->label('Status')
                                ->required(),
                            Forms\Components\Toggle::make('is_closed')
                                ->label('No longer operating (Permenant/Temporary Closure)')
                                ->onIcon('heroicon-s-check-circle')
                                ->offIcon('heroicon-s-x-circle'),
                        ])
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
                            ->getSearchResultsUsing(
                                fn(string $search) => Location::where('name', 'like', "%{$search}%")
                                    ->orWhere('address', 'like', "%{$search}%")
                                    ->limit(25)
                                    ->get()
                                    ->mapWithKeys(function ($location) {
                                        return [$location->id => $location->name . ' - ' . $location->full_address];
                                    })
                            )
                            ->hidden(fn($get) => $get('location_type') === 'manual')
                            ->getOptionLabelUsing(fn($value): ?string => Location::find($value)?->name . ' - ' . Location::find($value)?->full_address)
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

                        Forms\Components\Toggle::make('is_hq')
                            ->label('Is headquarter ?')
                            ->onIcon('heroicon-s-check-circle')
                            ->offIcon('heroicon-s-x-circle'),
                        Forms\Components\Textarea::make('address')
                            ->disabled(fn($get) => $get('location_type') === 'existing')
                            ->required(),
                        Forms\Components\TextInput::make('address_postcode')
                            ->disabled(fn($get) => $get('location_type') === 'existing')
                            ->required(),

                        Grid::make(2)
                            ->schema([
                                Select::make('state_id')
                                    ->columnSpan(1)
                                    ->label('State')
                                    ->preload()
                                    ->searchable()
                                    ->relationship('state', 'name')
                                    ->required(),

                                Select::make('country_id')
                                    ->columnSpan(1)
                                    ->label('Country')
                                    ->preload()
                                    ->searchable()
                                    ->relationship('country', 'name')
                                    ->required(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('lang')
                                    ->columnSpan(1)
                                    ->label('Latitude')
                                    ->helperText('This is to locate your store in the map. Leave 0 if not sure')
                                    ->disabled(fn($get) => $get('location_type') === 'existing'),
                                Forms\Components\TextInput::make('long')
                                    ->columnSpan(1)
                                    ->label('Logitude')
                                    ->helperText('This is to locate your store in the map. Leave 0 if not sure')
                                    ->disabled(fn($get) => $get('location_type') === 'existing'),
                            ]),

                        SpatieMediaLibraryFileUpload::make('company_photos')
                            ->label('Store Photos')
                            ->multiple()
                            ->maxFiles(7)
                            ->collection(Store::MEDIA_COLLECTION_PHOTOS)
                            ->columnSpan('full')
                            ->disk(function () {
                                if (config('filesystems.default') === 's3') {
                                    return 's3_public';
                                }
                            })
                            ->acceptedFileTypes(['image/*'])
                            ->enableDownload(true)
                            ->rules('image'),
                        Repeater::make('menus')
                            ->label('Menus')
                            ->createItemButtonLabel('Add Menu')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Menu Name')
                                    ->reactive(),
                                FileUpload::make('file')
                                    ->label('Menu File (PDF ONLY)')
                                    ->enableDownload(true)
                                    ->disk(function () {
                                        if (config('filesystems.default') === 's3') {
                                            return 's3_public';
                                        }
                                    })
                                    ->acceptedFileTypes(['application/pdf'])
                                    ->rules('mimes:pdf')
                                    ->getUploadedFileUrlUsing(function ($file) {
                                        $disk = config('filesystems.default');
                                        if (config('filesystems.default') === 's3') {
                                            $disk = 's3_public';
                                        }
                                        return Storage::disk($disk)->url($file);
                                    }),
                            ])
                    ]),

                Group::make()
                    ->columnSpanFull()
                    ->columns([
                        'xs' => 1,
                        'lg' => 2,
                        'md' => 2,
                    ])
                    ->schema([
                        Section::make('Store Business Hours')
                        ->columnSpan(1)
                        ->schema([
                            Repeater::make('business_hours')
                                ->orderable(false)
                                ->cloneable(true)
                                ->disableLabel(true)
                                ->disableItemCreation(function ($get){
                                    // if items are 7 days then disable item creation
                                    if (count($get('business_hours')) == 7) {
                                        return true;
                                    }
                                    return false;
                                })
                                ->default(function () {
                                    $days = 7;
                                    $defaultHours = [];
                                    for ($i = 1; $i <= $days; $i++) {
                                        $defaultHours[] = [
                                            'day' => $i,
                                            'open_time' => '09:00',
                                            'close_time' => '18:00',
                                        ];
                                    }
                                    return $defaultHours;
                                })
                                ->schema([
                                    Grid::make(3)
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
                                                ->label('Day'),
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
                                                ->default('18:00')
                                                ->label('Close Time'),
                                        ]),
                                ])
                        ]),
                        Section::make('Store Rest Hours')
                            ->columnSpan(1)
                            ->schema([
                                Repeater::make('rest_hours')
                                    ->default([])
                                    ->orderable(false)
                                    ->cloneable(true)
                                    ->disableLabel(true)
                                    ->minItems(0)
                                    ->schema([
                                        Grid::make(3)
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
                                                ->label('Day'),
                                                TimePicker::make('open_time')
                                                    ->withoutSeconds()
                                                    ->withoutDate()
                                                    ->required()
                                                    ->default('13:00')
                                                    ->label('Start Time'),
                                                TimePicker::make('close_time')
                                                    ->withoutSeconds()
                                                    ->withoutDate()
                                                    ->required()
                                                    ->default('16:00')
                                                    ->label('End Time'),
                                            ]) // end grid
                                    ]) // end repeater
                            ]) // end seciton store rest hours
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->enum(Store::STATUS)
                    ->colors([
                        'secondary' => 0,
                        'success' => 1,
                    ])
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->formatStateUsing(fn($state) => $state ?? 'Un-onboarded')
                    ->sortable()
                    ->label('Linked User Account'),

                Tables\Columns\TextColumn::make('merchant.name')
                    ->formatStateUsing(fn($state) => $state ?? 'Un-onboarded')
                    ->sortable()
                    ->label('Merchant Name'),

                TagsColumn::make('categories.name')
                    ->label('Categories')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('business_phone_no'),
                Tables\Columns\TextColumn::make('address')
                    // format state to truncate string ...
                    ->formatStateUsing(fn($state) => substr($state, 0, 20) . '...')
                    ->searchable(),

                Tables\Columns\TextColumn::make('address_postcode')
                    ->sortable()
                    ->searchable(),

                // Tables\Columns\TextColumn::make('lang'),
                // Tables\Columns\TextColumn::make('long'),
                Tables\Columns\ToggleColumn::make('is_hq')
                    ->label('Headquarter'),

                Tables\Columns\ToggleColumn::make('is_closed')
                    ->label('Is Closed')
            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->options([
                                'all' => 'All',
                                'unboarded' => 'Un-onboarded',
                                'onboarded' => 'Onboarded',
                            ])
                            ->label('Onboard Status')
                            ->default('all')
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['user_id'] === 'unboarded') {
                            return $query->whereNull('user_id');
                        } elseif ($data['user_id'] === 'onboarded') {
                            return $query->whereNotNull('user_id');
                        } else {
                            return $query;
                        }
                    }),
                SelectFilter::make('status')
                    ->options(Store::STATUS)
                    ->label('Status'),
                Filter::make('categories')
                    ->form([
                        Select::make('categories')
                            ->options(function () {
                                return MerchantCategory::pluck('name', 'id');
                            })
                            ->placeholder('Select Category'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!$data['categories']) {
                            // no filter
                        } else {
                            if (isset($data['categories'])) {
                                $query->whereHas('categories', function (Builder $query) use ($data) {
                                    $query->whereIn('merchant_category_id', (array) $data['categories']);
                                });
                            }
                        }
                    })
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                //                 Tables\Actions\DeleteBulkAction::make(),
                ExportBulkAction::make()
                    ->exports([
                        ExcelExport::make()
                            ->label('Export Stores Categories')
                            ->withColumns([
                                Column::make('id')->heading('store_id'),
                                Column::make('name')->heading('store_name'),
                                Column::make('categories.name')
                                    ->heading('category_names')
                                    ->getStateUsing(fn($record) => $record->categories->pluck('name')->join(',')),
                                Column::make('status')
                                    ->heading('status')
                                    ->getStateUsing(fn($record) => Store::STATUS[$record->status]),
                            ])
                            ->withFilename(fn($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
                            ->withWriterType(\Maatwebsite\Excel\Excel::CSV)
                    ]),

                // bulk action for changing status
                BulkAction::make('change_status')
                    ->label('Change Status')
                    ->icon('heroicon-o-refresh')
                    ->form([
                        Select::make('status')
                            ->options(Store::STATUS)
                            ->default(Store::STATUS_ACTIVE)
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        foreach ($records as $record) {
                            $record->update(['status' => $data['status']]);
                        }
                    })->deselectRecordsAfterCompletion(),
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
