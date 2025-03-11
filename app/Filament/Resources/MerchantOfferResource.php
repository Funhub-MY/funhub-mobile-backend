<?php

namespace App\Filament\Resources;

use Closure;
use Filament\Forms;
use App\Models\User;
use Filament\Forms\Components\Repeater;
use Filament\Tables;
use App\Models\Store;
use App\Models\Merchant;
use Filament\Resources\Form;
use App\Models\MerchantOffer;
use Filament\Resources\Table;
use App\Models\MerchantCategory;
use Filament\Resources\Resource;
use Illuminate\Support\Collection;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Fieldset;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Actions\ReplicateAction;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Filament\Resources\MerchantOfferResource\Pages;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\MerchantOfferResource\RelationManagers;
use App\Models\MerchantOfferCampaign;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Filters\SelectFilter;
use Google\Service\StreetViewPublish\Place;
use Illuminate\Support\Facades\DB;

class MerchantOfferResource extends Resource
{
    protected static ?string $model = MerchantOffer::class;

    protected static ?string $navigationIcon = 'heroicon-o-cash';

    protected static ?string $modelLabel = 'Merchant Offer';

    protected static ?string $navigationGroup = 'Merchant Offers';

    protected static ?int $navigationSort = 1;
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
        $locales = config('app.available_locales');

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
                                    ->collection(MerchantOffer::MEDIA_COLLECTION_NAME)
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
                                    ->collection(MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER)
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

                                // Forms\Components\TextInput::make('name')
                                //     ->afterStateHydrated(function ($state, $component, $record, $get) {
                                //         $translations = json_decode($record->name_translations ?? '{}', true);
                                //         $language = $get('language') ?? app()->getLocale();
                                //         $translatedValue = Arr::get($translations, $language, $record->name);
                                //         $component->state($translatedValue);
                                //     })
                                //     ->reactive()
                                //     ->required(),
                                TextInput::make('name')
                                    ->required(),

                                Forms\Components\TextInput::make('sku')
                                    ->label('SKU')
                                    ->required(),

                                Forms\Components\DateTimePicker::make('available_at')
                                    ->required()
                                    ->minDate(fn($livewire) => $livewire instanceof EditRecord ? $livewire->record->available_at : now()->startOfDay()),
                                Forms\Components\DateTimePicker::make('available_until')
                                    ->required()
                                    ->minDate(fn($livewire) => $livewire instanceof EditRecord ? $livewire->record->available_at : now()->startOfDay()),
                                Forms\Components\TextInput::make('expiry_days')
                                    ->label('Expire in (Days) After Purchase')
                                    ->helperText('Leave blank if no expiry. Available until user redeemed it.')
                                    ->numeric(),

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
                                Forms\Components\Textarea::make('description')
                                    ->rows(5)
                                    ->cols(10)
                                    ->columnSpan('full')
                                    ->required(),
                                Forms\Components\Textarea::make('fine_print')
									->label('T&C')
                                    ->rows(5)
                                    ->cols(10)
                                    ->columnSpan('full'),
                                Forms\Components\Textarea::make('redemption_policy')
                                    ->rows(5)
                                    ->cols(10)
                                    ->columnSpan('full'),
                                Forms\Components\Textarea::make('cancellation_policy')
                                    ->rows(5)
                                    ->cols(10)
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
                                                ->padFractionalZeros(true)
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
                                                ->padFractionalZeros(true)
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
                            ])->columns(2)
                    ])->columnSpan(['lg' => 2]),
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Stock')
                            ->schema([
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Available Quantity')
                                    ->required()
                                    ->numeric()
                                    ->disabledOn('edit')
                                    // ->helperText('Quantity field will be locked after created offer. Please add more vouchers using "Vouchers" below.')
                                    // ->disabled(fn ($livewire) => $livewire instanceof EditRecord)
                                    ->minValue(1),
                            ])->columns(1),
                        Forms\Components\Section::make('Other')
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->options(MerchantOffer::STATUS)->default(0),

                                Toggle::make('available_for_web')
                                    ->label('Available for Web')
                                    ->helperText('If enabled, this offer will be shown in Funhub Merchant Web.')
                                    ->default(false),

                                DatePicker::make('publish_at')
                                    ->label('Publish Date')
                                    ->visible(fn(Closure $get) => $get('status') == MerchantOffer::STATUS_DRAFT)
                                    ->minDate(now()->addDay()->startOfDay())
                                    ->helperText('System will change status to Published if publish date is set, change happen at 00:01 of Date.'),
                                // Forms\Components\Select::make('user_id')
                                //     ->label('Merchant User')
                                //     ->searchable()
                                //     ->getSearchResultsUsing(fn (string $search) => User::whereHas(['merchant' => fn ($q) => $q->where('merchants.status', Merchant::STATUS_APPROVED)])
                                //         ->where('name', 'like', "%{$search}%")
                                //         ->limit(25)
                                //     )
                                //     ->getOptionLabelFromRecordUsing(fn ($record) => $record->name.' ('.$record->email.')')
                                //     ->required()
                                //     ->reactive()
                                //     ->helperText('Users who has merchant profile created.')
                                //     ->relationship('user', 'name'),
                                Forms\Components\Select::make('user_id')
                                    ->label('Merchant User')
                                    ->relationship('user', 'name')
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search): array {
                                        return User::query()
                                            ->where(function ($q) use ($search) {
                                                $q->where('name', 'LIKE', "%{$search}%")
                                                    ->orWhere('email', 'LIKE', "%{$search}%")
                                                    ->orWhere('phone_no', 'LIKE', "%{$search}%")
                                                    ->orWhere('username', 'LIKE', "%{$search}%");
                                            })
                                            ->whereHas('merchant', function ($q) {
                                                $q->where('merchants.status', Merchant::STATUS_APPROVED);
                                            })
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(function ($user) {
                                                return [
                                                    $user->id => $user->name . ($user->username ? " (username: {$user->username}, ID: {$user->id})" : '')
                                                ];
                                            })
                                            ->toArray();
                                    })
                                    ->getOptionLabelUsing(function ($value): string {
                                        $user = User::find($value);
                                        return $user ? $user->name . ($user->username ? " (username: {$user->username}, ID: {$user->id})" : '') : '';
                                    })
                                    ->helperText('Users who has merchant profile created.')
                                    ->required()
                                    ->reactive(),
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
                                    ->required()
                                    ->preload()
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
                                            ->unique(MerchantCategory::class, 'slug', ignoreRecord: true),
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
                Tables\Columns\TextColumn::make('campaign.name')
                    ->url(fn ($record) => (isset($record->campaign)) ? route('filament.resources.merchant-offer-campaigns.edit', $record->campaign) : null)
                    ->searchable()
                    ->sortable(),
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
                    ->enum(MerchantOffer::STATUS)
                    ->colors([
                        'secondary' => 0,
                        'success' => 1,
                    ])
                    ->sortable()
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('available_for_web')
                    ->enum([
                        0 => 'No',
                        1 => 'Yes',
                    ])
                    ->colors([
                        'secondary' => 0,
                        'success' => 1,
                    ])
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('available_at')
                    ->sortable(),
                Tables\Columns\TextColumn::make('available_until')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('By User'),
                Tables\Columns\TextColumn::make('store.name')
                    ->default('-')
                    ->label('By Store'),
                Tables\Columns\TextColumn::make('expiry_days')
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku')
                    ->searchable()
                    ->sortable(),
                // created_at sortable
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                // filter by campaign relation
                SelectFilter::make('campaign_id')
                    ->label('Campaign')
                    ->searchable()
                    ->options(function () {
                        return MerchantOfferCampaign::select('id', DB::raw("CONCAT(name, ' (', sku, ')') as name"))
                            ->get()
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->relationship('campaign', 'name'),

                Filter::make('available_for_web')
                    ->form([
                        Select::make('available_for_web')
                            ->options([
                                0 => 'No',
                                1 => 'Yes',
                            ])
                            ->default(0)
                            ->required(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['available_for_web'],
                                fn(Builder $query, $status): Builder => $query->where('available_for_web', $status),
                            );
                    }),

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
                    ->options(MerchantOffer::STATUS),

                // Filter::make('user')
                //     ->label('Merchant User')
                //     ->form([
                //         Select::make('user_id')
                //             ->relationship('user', 'name')
                //             ->searchable()
                //             // ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                //             ->helperText('Users who has merchant profile created.')
                //     ])
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                ReplicateAction::make('duplicate')
                    ->excludeAttributes(['quantity', 'available_at', 'available_until', 'publish_at', 'status'])
                    ->mountUsing(fn (Forms\ComponentContainer $form, MerchantOffer $record) => $form->fill([
                    'sku' => $record->sku,
                    'idOfModelToBeReplicate' => $record->id,
                    ]))
                    ->form([
                        Hidden::make('idOfModelToBeReplicate'),
                        Forms\Components\Select::make('status')
                        ->options(MerchantOffer::STATUS)->default(0),
                        Forms\Components\TextInput::make('sku')
                            ->label('SKU')
                            ->required(),
                        Forms\Components\DateTimePicker::make('available_at')
                            ->required(),
                        Forms\Components\DateTimePicker::make('available_until')
                            ->required(),
                        DatePicker::make('publish_at')
                            ->label('Publish Date')
                            ->visible(fn(Closure $get) => $get('status') == MerchantOffer::STATUS_DRAFT)
                            ->minDate(now()->addDay()->startOfDay())
                            ->helperText('System will change status to Published if publish date is set, change happen at 00:01 of Date.'),
                        Forms\Components\TextInput::make('quantity')
                            ->label('Available Quantity')
                            ->required()
                            ->numeric()
                            ->minValue(1),
                    ])
                    ->beforeReplicaSaved(function (MerchantOffer $replica, array $data): void {
                        $replica->fill($data);
                    })
                    ->afterReplicaSaved(function (MerchantOffer $replica, array $data): void {
                        $idOfModelToBeReplicated = $data['idOfModelToBeReplicate'];

                        // Retrieve the images associated with the original model.
                        $originalMediaCollectionNameImgs = Media::where('model_id', $idOfModelToBeReplicated)
                            ->where('collection_name', MerchantOffer::MEDIA_COLLECTION_NAME)
                            ->get();

                        foreach ($originalMediaCollectionNameImgs as $originalMediaCollectionNameImg) {
                            // Copy the image to the new model.
                            $replica
                            ->addMediaFromDisk($originalMediaCollectionNameImg->getPath(), (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default')))
                            ->preservingOriginal()
                            ->toMediaCollection(MerchantOffer::MEDIA_COLLECTION_NAME,
                            (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default')),
                        );
                        }

                        $originalHorizontalBannerImgs = Media::where('model_id', $idOfModelToBeReplicated)
                            ->where('collection_name', MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER)
                            ->get();

                        foreach ($originalHorizontalBannerImgs as $originalHorizontalBannerImg) {
                            // Copy the image to the new model.
                            $replica
                            ->addMediaFromDisk($originalHorizontalBannerImg->getPath(), (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default')))
                            ->preservingOriginal()
                            ->toMediaCollection(MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER,
                            (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default')),);
                        }

                        redirect()->route('filament.resources.merchant-offers.edit', $replica);

                    })
                    ->icon('heroicon-s-document-duplicate'),
                // Action::make('duplicate')
                //     ->mountUsing(fn (Forms\ComponentContainer $form, MerchantOffer $record) => $form->fill([
                //         'sku' => $record->flight_date,
                //     ]))
                //     ->action(function (MerchantOfferResource $record, array $data): void {
                //         $record->fill($data);
                //         $record->duplicate();
                //     })
                //     ->form([
                //         Forms\Components\Select::make('status')
                //             ->options(MerchantOffer::STATUS)->default(0),
                //         Forms\Components\TextInput::make('sku')
                //             ->label('SKU')
                //             ->required(),
                //         Forms\Components\DateTimePicker::make('available_at')
                //             ->required(),
                //         Forms\Components\DateTimePicker::make('available_until')
                //             ->required(),
                //         DatePicker::make('publish_at')
                //             ->label('Publish Date')
                //             ->visible(fn(Closure $get) => $get('status') == MerchantOffer::STATUS_DRAFT)
                //             ->minDate(now()->addDay()->startOfDay())
                //             ->helperText('System will change status to Published if publish date is set, change happen at 00:01 of Date.'),
                //         Forms\Components\TextInput::make('quantity')
                //             ->label('Available Quantity')
                //             ->required()
                //             ->numeric()
                //             ->minValue(1),
                //     ])
                //     ->icon('heroicon-s-document-duplicate'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),

                // bulk action toggle available_for_web
                Tables\Actions\BulkAction::make('toggle_available_for_web')
                    ->label('Toggle Available for Web')
                    ->form([
                        Toggle::make('available_for_web')
                            ->default(true)
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                       // mass update available_for_web for records
                       MerchantOffer::whereIn('id', $records->pluck('id'))
                           ->update(['available_for_web' => $data['available_for_web']]);

                        Notification::make()
                            ->success()
                            ->title('Successfully updated '.$records->count().' offers')
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion(),
                    
                // Force sync with Algolia Scout
                Tables\Actions\BulkAction::make('force_sync_algolia')
                    ->label('Force Sync with Algolia')
                    ->modalContent(fn () => new HtmlString("Use this when you make same day edits to Offers"))
                    ->icon('heroicon-o-refresh')
                    ->action(function (Collection $records): void {
                        $successCount = 0;
                        $errorCount = 0;
                        
                        foreach ($records as $record) {
                            try {
                                // Force the record to be searchable in Algolia
                                $record->searchable();
                                $successCount++;
                            } catch (\Exception $e) {
                                $errorCount++;
                                Log::error('[MerchantOfferResource] Force Sync Algolia Error', [
                                    'record_id' => $record->id,
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString(),
                                ]);
                            }
                        }
                        
                        $message = "Successfully synced {$successCount} offers with Algolia";
                        if ($errorCount > 0) {
                            $message .= " ({$errorCount} errors)";
                        }
                        
                        Notification::make()
                            ->success()
                            ->title($message)
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion(),

                Tables\Actions\BulkAction::make('update_status')
                    ->hidden(fn () => auth()->user()->hasRole('merchant'))
                    ->label('Update Status')
                    ->icon('heroicon-o-refresh')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->options(MerchantOffer::STATUS)->default(0),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $success = 0;
                        $records->each(function (MerchantOffer $record) use ($data, $success) {
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
                    })->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // RelationManagers\ClaimedByUsersRelationManager::class,
            // RelationManagers\UsersRelationManager::class,
            RelationManagers\VouchersRelationManager::class,
            RelationManagers\LocationRelationManager::class,
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMerchantOffers::route('/'),
            'create' => Pages\CreateMerchantOffer::route('/create'),
            'edit' => Pages\EditMerchantOffer::route('/{record}/edit'),
        ];
    }
}
