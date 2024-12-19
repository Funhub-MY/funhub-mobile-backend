<?php

namespace App\Filament\Resources;

use Closure;
use App\Filament\Resources\MerchantOfferCampaignResource\Pages;
use App\Filament\Resources\MerchantOfferCampaignResource\RelationManagers;
use App\Models\Merchant;
use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferCampaignSchedule;
use App\Models\MerchantOfferCategory;
use App\Models\Store;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Form;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MerchantOfferCampaignResource extends Resource
{
    protected static ?string $model = MerchantOfferCampaign::class;


    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $modelLabel = 'Merchant Offer Campaigns';

    protected static ?string $navigationGroup = 'Merchant Offers';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        if (auth()->user()->hasRole('merchant')) {
            $query->where('user_id', auth()->user()->id);
        }

        return $query->withCount([
            'merchantOffers as total_vouchers_count' => function ($query) {
                $query->select(DB::raw('COALESCE(SUM(
                    (SELECT COUNT(*) FROM merchant_offer_vouchers 
                    WHERE merchant_offers.id = merchant_offer_vouchers.merchant_offer_id 
                    AND merchant_offers.merchant_offer_campaign_id = merchant_offer_campaigns.id
                    AND voided = false)
                ), 0)'));
            },
            'merchantOffers as sold_vouchers_count' => function ($query) {
                $query->select(DB::raw('COALESCE(SUM(
                    (SELECT COUNT(*) FROM merchant_offer_vouchers 
                    WHERE merchant_offers.id = merchant_offer_vouchers.merchant_offer_id 
                    AND merchant_offers.merchant_offer_campaign_id = merchant_offer_campaigns.id
                    AND owned_by_id IS NOT NULL 
                    AND voided = false)
                ), 0)'));
            },
            'merchantOffers as available_vouchers_count' => function ($query) {
                $query->select(DB::raw('COALESCE(SUM(
                    (SELECT COUNT(*) FROM merchant_offer_vouchers 
                    WHERE merchant_offers.id = merchant_offer_vouchers.merchant_offer_id 
                    AND merchant_offers.merchant_offer_campaign_id = merchant_offer_campaigns.id
                    AND owned_by_id IS NULL 
                    AND voided = false)
                ), 0)'));
            },
        ]);
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

								Repeater::make('highlight_messages')
									->label('Highlight Message')
									->createItemButtonLabel('Add Highlight Message')
									->schema([
										TextInput::make('message')
											->label('Message')
											->maxLength(255)
                                            ->required()
											->placeholder('Enter a highlight message'),
									])
									->maxItems(3)
									->columnSpan('full')
									->helperText('Maximum 3 highlighted message.'),

                                Forms\Components\Toggle::make('available_for_web')
                                    ->label('Available for Web')
                                    ->helperText('If enabled, this offer will be shown in Funhub Merchant Web.')
                                    ->default(false),

                                Forms\Components\TextInput::make('expiry_days')
                                    ->label('Expire in (Days) After Purchase')
                                    ->columnSpan(1)
                                    ->required()
                                    ->helperText('No of days aailable until user redeemed it. Will affect all vouchers generated under this campaign.')
                                    ->numeric(),

                                Forms\Components\Toggle::make('auto_move_vouchers')
                                    ->label('Auto Move Vouchers to Next Schedule If Unsold')
                                    ->columnSpan('full')
                                    ->default(true)
                                    ->helperText('Automatically move a schedule\'s unsold vouchers to the next schedule if enabled. If disabled, unsold vouchers will be expired after the schedule ends.'),

                                Forms\Components\Textarea::make('description')
                                    ->rows(5)
                                    ->cols(10)
                                    ->columnSpan('full')
                                    ->required(),
                                Forms\Components\Textarea::make('fine_print')
									->label('T&C')
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
                                                ->padFractionalZeros(true)
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
                                                ->padFractionalZeros(true)
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
                                                ->padFractionalZeros(true)
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
                                                ->padFractionalZeros(true)
                                                ->thousandsSeparator(','),
                                            ),
                                ]),
                            ])->columns(2),

                            Group::make()
                            ->visible(fn ($context) => ($context == 'create') ? true : false)
                            ->schema([
                                    Section::make('Schedule Generator')
                                        ->schema([
                                            Grid::make()
                                                ->schema([
                                                    DatePicker::make('start_date')
                                                        ->label('Start Date')
                                                        ->required(),

                                                    DatePicker::make('end_date')
                                                        ->label('End Date')
                                                        ->afterOrEqual('start_date'),

                                                    TextInput::make('vouchers_count')
                                                        ->required()
                                                        ->label('Total No. of Vouchers')
                                                        ->numeric()
                                                        ->reactive()
                                                        ->helperText('Total number of vouchers for this campaign'),
                                                        // ->suffixAction(
                                                        //     fn (TextInput $component): Action => 
                                                        //     Action::make('add_available')
                                                        //         ->icon('heroicon-s-plus')
                                                        //         ->tooltip('Add vouchers to this campaign')
                                                        //         ->action(function (array $data) {
                                                        //             if (!isset($data['vouchers_count']) || !$data['vouchers_count']) {
                                                        //                 Notification::make()
                                                        //                     ->title('Please specify the number of vouchers')
                                                        //                     ->danger()
                                                        //                     ->send();
                                                        //                 return;
                                                        //             }

                                                        //             $currentCount = $data['vouchers_count'] ?? 0;
                                                        //             $this->form->fill([
                                                        //                 'vouchers_count' => $currentCount,
                                                        //                 'available_quantity' => ceil($currentCount / ($data['days_per_schedule'] ?? 1))
                                                        //             ]);

                                                        //             Notification::make()
                                                        //                 ->title('Vouchers distribution updated')
                                                        //                 ->success()
                                                        //                 ->send();
                                                        //         }),
                                                        // ),

                                                    TextInput::make('interval_days')
                                                        ->label('Interval (Days)')
                                                        ->numeric()
                                                        ->helperText('Days between each schedule'),

                                                    TextInput::make('days_per_schedule')
                                                        ->label('Days Per Schedule')
                                                        ->numeric()
                                                        ->minValue(1)
                                                        ->required()
                                                        ->reactive()
                                                        ->helperText('Duration of each schedule in days'),

                                                    TextInput::make('available_quantity')
                                                        ->label('Available Quantity per Schedule')
                                                        ->numeric()
                                                        ->minValue(1),
                                                ])
                                                ->columns(2),
                                    ])
                                ])
                            ->columnSpan('full'),

                            Forms\Components\Group::make()
                            // visible on edit only
                            ->visible(fn ($context) => ($context == 'edit') ? true : false)
                            ->schema([
                                Forms\Components\Repeater::make('Schedules')
                                    ->orderable(false)
                                    ->reactive()
                                    ->hint('Each schedules represents one merchant offer')
                                    ->relationship('schedules')
                                    ->schema([
                                        Group::make()
                                            ->schema([
                                                Forms\Components\Select::make('status')
                                                    ->options(MerchantOfferCampaignSchedule::STATUS)->default(0),

                                                DatePicker::make('publish_at')
                                                    ->label('Publish Date')
                                                    ->helperText('System will change status to Published if publish date is set, change happen at 00:01 of Date.'),
                                            ])->columns(2),
                                        Group::make()
                                            ->schema([
                                                Forms\Components\DateTimePicker::make('available_at')
                                                    ->required()
                                                    ->columnSpan(1)
                                                    // disabled if available_at is past
                                                    ->disabled(fn($livewire, Closure $get) => $livewire instanceof EditRecord && $get('available_at') && Carbon::parse($get('available_at'))->isPast())
                                                    ->minDate(fn($get) =>  $get('available_at') ? Carbon::parse($get('available_at')) : now()->startOfDay()),
                                                Forms\Components\DateTimePicker::make('available_until')
                                                    ->required()
                                                    ->columnSpan(1)
                                                    ->disabled(fn($livewire, Closure $get) => $livewire instanceof EditRecord && $get('available_until') && Carbon::parse($get('available_at'))->isPast())
                                                    ->minDate(fn($get) => $get('available_at') ? Carbon::parse($get('available_at')) : now()->startOfDay()),
                                                ])
                                            ->columns(2),
                                        Group::make()
                                            ->schema([
                                                Forms\Components\TextInput::make('expiry_days')
                                                    ->label('Expire in (Days) After Purchase')
                                                    ->columnSpan(1)
                                                    ->disabled(fn($livewire, Closure $get) => $livewire instanceof EditRecord && $get('available_until') && Carbon::parse($get('available_at'))->isPast())
                                                    ->helperText('If filled, vouchers specific to this schedule will expire in the number of days set above.')
                                                    ->numeric(),

                                                Forms\Components\TextInput::make('quantity')
                                                    ->label('Available Quantity')
                                                    ->required()
                                                    ->columnSpan(1)
                                                    ->numeric(),

                                                Placeholder::make('cannot_update_past')
                                                        ->visible(fn($livewire, Closure $get) => $livewire instanceof EditRecord && $get('available_until') && Carbon::parse($get('available_at'))->isPast())
                                                        ->columnSpan(2)
                                                        ->disableLabel()
                                                        ->content(new HtmlString('<span style="font-weight:bold; color: #ff0000">Cannot update schedule if schedule is already running (past available at date time)</span>')),

                                                Hidden::make('user_id')
                                                    ->default(fn () => auth()->id()),
                                            ])->columns(2)
                                    ])
                            ]),
                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Merchant')
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->label('Merchant User')
                                    ->searchable()
                                    ->getSearchResultsUsing(fn (string $search) => User::whereHas(['merchant' => fn ($q) => $q->where('merchants.status', Merchant::STATUS_APPROVED)])
                                        ->where('name', 'like', "%{$search}%")
                                        ->limit(25)
                                    )
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name.' ('.$record->email.')')
                                    ->required()
                                    ->reactive()
                                    ->helperText('Users who has merchant profile created.')
                                    ->relationship('user', 'name'),
                                Forms\Components\Select::make('stores')
                                    ->label('Stores')
                                    ->multiple()
                                    ->helperText('Must select store(s) else it won\'t appear in the Nearby Merchant Stores tab.')
                                    ->preload()
                                    ->reactive()
                                    ->relationship('stores', 'name', function (Builder $query, Closure $get) {
                                        $query->where('user_id', $get('user_id'));
                                    })
                                    ->hidden(fn (Closure $get) => $get('user_id') === null),
                            ])->columns(1),

                            Forms\Components\Section::make('Categories')->schema([
                                Forms\Components\Select::make('categories')
                                    ->label('Select Categories')
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

                            Forms\Components\Section::make('Global Vouchers')
                                ->schema([
                                    Placeholder::make('available_vouchers_create')
                                    ->reactive()
                                    ->label('Campaign Vouchers Status')
                                    ->content(function (Closure $get, $record) {
                                        if (!$record) return 'Save the campaign first to see voucher status';
                                        
                                        $offers = \App\Models\MerchantOffer::where('merchant_offer_campaign_id', $record->id)
                                            ->withCount([
                                                'vouchers as available_count' => function ($query) {
                                                    $query->whereNull('owned_by_id')
                                                        ->where('voided', false);
                                                },
                                                'vouchers as sold_count' => function ($query) {
                                                    $query->whereNotNull('owned_by_id')
                                                        ->where('voided', false);
                                                },
                                                'vouchers as total_count' => function ($query) {
                                                    $query->where('voided', false);
                                                }
                                            ])
                                            ->get();

                                        // if no offers exist yet but schedules exist, show the processing message
                                        if ($offers->isEmpty() && $record->schedules()->exists()) {
                                            return new HtmlString(
                                                "<div style='font-size: 1.2em; font-weight: 600;'>Merchant offers and vouchers are being generated</div>
                                                <div style='color: #666; margin-top: 4px;'>
                                                    Please refresh in a few minutes to see the updated status.
                                                </div>"
                                            );
                                        }
                                            
                                        $available = $offers->sum('available_count');
                                        $sold = $offers->sum('sold_count');
                                        $total = $offers->sum('total_count');
                                            
                                        return new HtmlString(
                                            "<div style='font-size: 1.2em; font-weight: 600;'>
                                                {$sold} sold / {$available} available
                                            </div>
                                            <div style='color: #666; margin-top: 4px;'>
                                                Total vouchers in this campaign: {$total}
                                            </div>"
                                        );
                                    })
                                    ->columnSpan(1),
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

                Tables\Columns\TextColumn::make('user.name')
                    ->label('By User'),
                Tables\Columns\TextColumn::make('store.name')
                    ->default('-')
                    ->label('By Store'),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Funhub')
                    ->sortable(),


                Tables\Columns\TextColumn::make('sku')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('vouchers_count')
                    ->label('Total Vouchers')
                    ->formatStateUsing(fn ($record): string => 
                        (string)$record->total_vouchers_count),

                Tables\Columns\TextColumn::make('vouchers_status')
                ->label('Sold / Available')
                ->formatStateUsing(fn ($record): string => 
                    "{$record->sold_vouchers_count} / {$record->available_vouchers_count}")
                ->description(fn ($record): string =>
                    "Total: {$record->total_vouchers_count}")
                ->color(fn ($record): string =>
                    $record->available_vouchers_count > 0 ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('upcoming_vouchers_count')
                    ->label('Upcoming Vouchers')
                    ->sum('upcomingSchedules', 'quantity')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->searchable(),
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
                // Tables\Filters\SelectFilter::make('status')
                //     ->label('Status')
                //     ->options(MerchantOfferCampaign::STATUS),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                // Tables\Actions\DeleteBulkAction::make(), // disable delete bulk action, only allow user to archive it.
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

                                try {
                                    // archive all associated merchant offers
                                    $record->schedules()->update(['status' => $data['status']]);
                                    $record->merchantOffers()->update(['status' => $data['status']]);
                                } catch (\Exception $e) {
                                    Log::error('[MerchantOfferCampaignResource] Bulk Update Status Error', [
                                        'record' => $record->toArray(),
                                        'data' => $data,
                                        'error' => $e->getMessage(),
                                    ]);
                                }

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
                                ->title('Successfully updated '.$success.' offers status to' . MerchantOfferCampaign::STATUS[$data['status']])
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
