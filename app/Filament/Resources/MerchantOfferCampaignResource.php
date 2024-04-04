<?php

namespace App\Filament\Resources;

use Closure;
use App\Filament\Resources\MerchantOfferCampaignResource\Pages;
use App\Filament\Resources\MerchantOfferCampaignResource\RelationManagers;
use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferCategory;
use App\Models\Store;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Resources\Form;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class MerchantOfferCampaignResource extends Resource
{
    protected static ?string $model = MerchantOfferCampaign::class;


    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $modelLabel = 'Merchant Offer Campaigns';

    protected static ?string $navigationGroup = 'Merchant';

    public static function getEloquentQuery(): Builder
    {
        $query = static::getModel()::query();
        if (auth()->user()->hasRole('merchant')) {
            $query->where('user_id', auth()->user()->id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Card::make()
                            ->schema([
                                Forms\Components\SpatieMediaLibraryFileUpload::make('gallery')
                                    ->label('Offer Images')
                                    ->multiple()
                                    ->required()
                                    ->collection(MerchantOfferCampaign::MEDIA_COLLECTION_NAME)
                                    ->columnSpan('full')
                                    ->customProperties(['is_cover' => false])
                                    // disk is s3_public
                                    ->disk(function () {
                                        if (config('filesystems.default') === 's3') {
                                            return 's3_public';
                                        }
                                    })
                                    ->acceptedFileTypes(['image/*'])
                                    ->maxFiles(20)
                                    ->rules('image'),

                                Forms\Components\SpatieMediaLibraryFileUpload::make('horizontal_banner')
                                    ->label('Horizontal Banner (In Articles)')
                                    ->maxFiles(1)
                                    ->required()
                                    ->collection(MerchantOfferCampaign::MEDIA_COLLECTION_HORIZONTAL_BANNER)
                                    ->columnSpan('full')
                                    ->customProperties(['is_cover' => false])
                                    // disk is s3_public
                                    ->disk(function () {
                                        if (config('filesystems.default') === 's3') {
                                            return 's3_public';
                                        }
                                    })
                                    ->acceptedFileTypes(['image/*'])
                                    ->rules('image'),

                                Forms\Components\TextInput::make('name')
                                    ->required(),

                                Forms\Components\TextInput::make('sku')
                                    ->label('Campaign Code (SKU)')
                                    ->helperText('Offers created will suffix number. eg. ABC122 will be ABC122-1, ABC122-2, etc.')
                                    ->required(),

                                Forms\Components\Toggle::make('flash_deal')
                                    ->label('Flash Deal')
                                    ->helperText('If enabled, this offer will be shown in Flash Deal section in the app. Use Available At & Until to set the Flash deals countdown')
                                    ->default(false),

                                Forms\Components\TextInput::make('expiry_days')
                                    ->label('Expire in (Days) After Purchase')
                                    ->columnSpan(1)
                                    ->helperText('Leave blank if no expiry. Available until user redeemed it. Will affect all vouchers generated under this campaign.')
                                    ->numeric(),

                                Forms\Components\Textarea::make('description')
                                    ->rows(5)
                                    ->cols(10)
                                    ->columnSpan('full')
                                    ->required(),
                                Forms\Components\Textarea::make('fine_print')
                                    ->rows(5)
                                    ->cols(10)
                                    ->required()
                                    ->columnSpan('full'),
                                Forms\Components\Textarea::make('redemption_policy')
                                    ->rows(5)
                                    ->cols(10)
                                    ->required()
                                    ->columnSpan('full'),
                                Forms\Components\Textarea::make('cancellation_policy')
                                    ->rows(5)
                                    ->cols(10)
                                    ->required()
                                    ->columnSpan('full'),
                            ])->columns(2),

                        Forms\Components\Card::make()
                            ->schema([
                                Select::make('purchase_method')
                                    ->label('Default Purchase Mode')
                                    ->helperText('This will show as default when user purchasing.')
                                    ->default('point')
                                    ->options([
                                        'point' => 'Funhub Point',
                                        'fiat' => 'MYR',
                                    ]),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Funhub Point Cost')
                                    ->required()
                                    ->numeric()
                                    ->mask(fn (Forms\Components\TextInput\Mask $mask) => $mask
                                        ->numeric()
                                        ->decimalPlaces(2)
                                        ->minValue(1)
                                        ->thousandsSeparator(',')
                                    ),

                                Fieldset::make('Point Pricing (MYR)')
                                    ->schema([
                                        Forms\Components\TextInput::make('point_fiat_price')
                                            ->label('Funhub Cost in MYR')
                                            ->required()
                                            ->numeric()
                                            ->prefix('RM')
                                            ->mask(fn (Forms\Components\TextInput\Mask $mask) => $mask
                                                ->numeric()
                                                ->decimalPlaces(2)
                                                ->minValue(1)
                                                ->thousandsSeparator(','),
                                            ),
                                    Forms\Components\TextInput::make('discounted_point_fiat_price')
                                        ->label('Discounted Funhub Cost in MYR')
                                            ->required()
                                            ->numeric()
                                            ->prefix('RM')
                                            ->mask(fn (Forms\Components\TextInput\Mask $mask) => $mask
                                                ->numeric()
                                                ->decimalPlaces(2)
                                                ->minValue(1)
                                                ->thousandsSeparator(','),
                                            ),
                                ]),

                                Fieldset::make('MYR Pricing')
                                    ->schema([
                                        Forms\Components\TextInput::make('fiat_price')
                                            ->label('MYR Cost')
                                            ->required()
                                            ->numeric()
                                            ->prefix('RM')
                                            ->mask(fn (Forms\Components\TextInput\Mask $mask) => $mask
                                                ->numeric()
                                                ->decimalPlaces(2)
                                                ->minValue(1)
                                                ->thousandsSeparator(','),
                                            ),
                                        Forms\Components\TextInput::make('discounted_fiat_price')
                                            ->label('MYR Discounted Cost')
                                            ->required()
                                            ->numeric()
                                            ->prefix('RM')
                                            ->mask(fn (Forms\Components\TextInput\Mask $mask) => $mask
                                                ->numeric()
                                                ->decimalPlaces(2)
                                                ->minValue(1)
                                                ->thousandsSeparator(','),
                                            ),
                                ]),
                            ])->columns(2),


                            Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\Repeater::make('Schedules')
                                    ->relationship('schedules')
                                    ->schema([
                                        Group::make()
                                            ->schema([
                                                Forms\Components\DateTimePicker::make('available_at')
                                                    ->required()
                                                    ->columnSpan(1)
                                                    ->minDate(fn($livewire) => $livewire instanceof EditRecord ? $livewire->record->available_at : now()->startOfDay()),
                                                Forms\Components\DateTimePicker::make('available_until')
                                                    ->required()
                                                    ->columnSpan(1)
                                                    ->minDate(fn($livewire) => $livewire instanceof EditRecord ? $livewire->record->available_at : now()->startOfDay()),
                                            ])
                                            ->columns(2),
                                        Group::make()
                                            ->schema([
                                                Forms\Components\TextInput::make('expiry_days')
                                                    ->label('Expire in (Days) After Purchase')
                                                    ->columnSpan(1)
                                                    ->helperText('If filled, vouchers specific to this schedule will expired in days set above.')
                                                    ->numeric(),
                                                Forms\Components\TextInput::make('quantity')
                                                    ->label('Available Quantity')
                                                    ->required()
                                                    ->columnSpan(1)
                                                    ->numeric()
                                                    ->disabledOn('edit')
                                                    // ->helperText('Quantity field will be locked after created offer. Please add more vouchers using "Vouchers" below.')
                                                    // ->disabled(fn ($livewire) => $livewire instanceof EditRecord)
                                                    ->minValue(1),

                                                Hidden::make('user_id')
                                                    ->default(fn () => auth()->id()),
                                            ])->columns(2)
                                    ])
                            ]),
                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Other')
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->options(MerchantOfferCampaign::STATUS)->default(0),
                                DatePicker::make('publish_at')
                                    ->label('Publish Date')
                                    ->visible(fn(Closure $get) => $get('status') == MerchantOfferCampaign::STATUS_DRAFT)
                                    ->minDate(now()->addDay()->startOfDay())
                                    ->helperText('System will change status to Published if publish date is set, change happen at 00:01 of Date.'),
                                Forms\Components\Select::make('user_id')
                                    ->label('Merchant User')
                                    ->searchable()
                                    ->getSearchResultsUsing(fn (string $search) => User::whereHas('merchant')
                                        ->where('name', 'like', "%{$search}%")->limit(25)
                                    )
                                    ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                                    ->required()
                                    ->reactive()
                                    ->helperText('Users who has merchant profile created.')
                                    ->afterStateUpdated(fn (callable $set) => $set('store_id', null))
                                    ->relationship('user', 'name'),
                                Forms\Components\Select::make('store_id')
                                    ->options(function (callable $get) {
                                        $user = User::where('id', $get('user_id'))->first();
                                        if ($user) {
                                            return $user->stores->pluck('name', 'id');
                                        }
                                        // TODO:: pluck all first until permissions and roles is up and running.
                                        return Store::all()->pluck('id', 'name');
                                    })
                                    ->hidden(fn (Closure $get) => $get('user_id') === null)
                                    ->searchable()
                                    ->label('Store')
                                    ->helperText('Optional, by selecting this will make the offers only applicable to the selected store.')
                                    ->nullable()
                            ])->columns(1),

                            Forms\Components\Section::make('Categories')->schema([
                                Forms\Components\Select::make('categories')
                                    ->label('')
                                    ->preload()
                                    ->required()
                                    ->relationship('allOfferCategories', 'name')->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->placeholder('Category name'),

                                        Select::make('parent_id')
                                            ->label('Parent Category')
                                            ->relationship('parent', 'name')
                                            ->preload()
                                            ->nullable(),
                                        // slug
                                        Forms\Components\TextInput::make('slug')
                                            ->required()
                                            ->placeholder('Category slug')
                                            ->helperText('Must not have space, replace space with dash. eg. food-and-beverage')
                                            ->unique(MerchantOfferCategory::class, 'slug', ignoreRecord: true),
                                        Forms\Components\RichEditor::make('description')
                                            ->placeholder('Category description'),
                                        // hidden user id is logged in user
                                        Forms\Components\Hidden::make('user_id')
                                            ->default(fn () => auth()->id()),
                                    ])
                                    ->multiple()
                                    ->searchable()
                                    ->placeholder('Select offer categories...'),
                            ])->columns(1),
                    ])->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->getStateUsing(function ($record) {
                        $name = $record->name;
                        if ($record->flash_deal) {
                            $name .= new HtmlString('<span class="font-bold ml-2 text-danger-700 uppercase">Flash</span>');
                        }

                        return $name;
                    })
                    ->html()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->enum(MerchantOfferCampaign::STATUS)
                    ->colors([
                        'secondary' => 0,
                        'success' => 1,
                    ])
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('By User'),
                Tables\Columns\TextColumn::make('store.name')
                    ->default('-')
                    ->label('By Store'),
                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Funhub')
                    ->sortable(),
                Tables\Columns\TextColumn::make('schedules_count')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                // filter by available_at and available_until date range
                Filter::make('availability')
                    ->form([
                        DatePicker::make('available_at'),
                        DatePicker::make('available_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['available_at'],
                                fn(Builder $query, $date): Builder => $query->whereDate('available_at', '>=', $date),
                            )
                            ->when(
                                $data['available_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('available_until', '<=', $date),
                            );
                    }),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(MerchantOfferCampaign::STATUS),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\BulkAction::make('update_status')
                    ->hidden(fn () => auth()->user()->hasRole('merchant'))
                    ->label('Update Status')
                    ->icon('heroicon-o-refresh')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->options(MerchantOfferCampaign::STATUS)->default(0),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $success = 0;
                        $records->each(function (MerchantOfferCampaign $record) use ($data, $success) {
                            try {
                                $record->update([
                                    'status' => $data['status'],
                                ]);
                                $success++;
                            } catch (\Exception $e) {
                                Log::error('[MerchantOfferResource] Bulk Update Status Error', [
                                    'record' => $record->toArray(),
                                    'data' => $data,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        });

                        if ($success > 0) {
                            Notification::make()
                            ->success()
                            ->title('Successfully updated '.$success.' offers status to' . MerchantOffer::STATUS[$data['status']])
                            ->send();
                        }
                    })
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
            'index' => Pages\ListMerchantOfferCampaigns::route('/'),
            'create' => Pages\CreateMerchantOfferCampaign::route('/create'),
            'edit' => Pages\EditMerchantOfferCampaign::route('/{record}/edit'),
        ];
    }
}
