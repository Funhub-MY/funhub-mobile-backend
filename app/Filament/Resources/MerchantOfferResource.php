<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MerchantOfferResource\Pages;
use App\Filament\Resources\MerchantOfferResource\RelationManagers;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantOffer;
use App\Models\Store;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Closure;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Actions\ReplicateAction;
use Filament\Tables\Actions\Action;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Filament\Tables\Actions\RestoreAction;

class MerchantOfferResource extends Resource
{
    protected static ?string $model = MerchantOffer::class;

    protected static ?string $navigationIcon = 'heroicon-o-cash';

    protected static ?string $modelLabel = 'Merchant Offer';

    protected static ?string $navigationGroup = 'Merchant';

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

                                Forms\Components\TextInput::make('name')
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
                                Forms\Components\Textarea::make('description')
                                    ->rows(5)
                                    ->cols(10)
                                    ->columnSpan('full')
                                    ->required(),
                                Forms\Components\Textarea::make('fine_print')
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
                                DatePicker::make('publish_at')
                                    ->label('Publish Date')
                                    ->visible(fn(Closure $get) => $get('status') == MerchantOffer::STATUS_DRAFT)
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
                                    ->relationship('categories', 'name')->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->placeholder('Category name'),
                                        // slug
                                        Forms\Components\TextInput::make('slug')
                                            ->required()
                                            ->placeholder('Category slug')
                                            ->unique(MerchantCategory::class, 'slug', ignoreRecord: true),
                                        Forms\Components\RichEditor::make('description')
                                            ->placeholder('Category description'),
                                        // hidden user id is logged in user
                                        Forms\Components\Hidden::make('user_id')
                                            ->default(fn () => auth()->id()),
                                    ])
                                    ->multiple()
                                    ->searchable()
                                    ->placeholder('Select categories...'),
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
                Tables\Columns\TextColumn::make('user.name')
                    ->label('By User'),
                Tables\Columns\TextColumn::make('store.name')
                    ->default('-')
                    ->label('By Store'),
                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Funhub')
                    ->sortable(),
                Tables\Columns\TextColumn::make('available_at')
                    ->sortable(),
                Tables\Columns\TextColumn::make('available_until')
                    ->sortable(),
                Tables\Columns\TextColumn::make('expiry_days')
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
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
                    })
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // RelationManagers\ClaimedByUsersRelationManager::class,
            // RelationManagers\UsersRelationManager::class,
            RelationManagers\VouchersRelationManager::class,
            RelationManagers\LocationRelationManager::class,
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
