<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use App\Models\MerchantOfferClaim;
use Filament\Forms\Components\Card;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Log;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use Pages\ViewMerchantOfferVoucher;
use App\Models\MerchantOfferVoucher;
use Filament\Forms\Components\Select;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\DateTimePicker;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use App\Filament\Resources\MerchantOfferVoucherResource\Pages;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\MerchantOfferVoucherResource\RelationManagers;
use App\Filament\Resources\MerchantOfferVoucherResource\Pages\MerchantOfferVouchersRelationManager;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferCampaignSchedule;
use App\Models\MerchantOfferVoucherMovement;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;

class MerchantOfferVoucherResource extends Resource
{
    protected static ?string $model = MerchantOfferVoucher::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $modelLabel = 'Stock Voucher';

    protected static ?string $navigationGroup = 'Merchant Offers';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        $query = static::getModel()::query();
        if (auth()->user()->hasRole('merchant')) {
            // whereHas offer with user_id = auth()->id()
            $query->whereHas('merchant_offer', function ($q) {
                $q->where('user_id', auth()->id());
            });
        }

        return $query;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Card::make([
                    Select::make('merchant_offer_id')
                        ->label('Attached to Merchant Offer')
                        ->relationship('merchant_offer', 'name')
                        ->preload()
                        ->searchable(),

                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->disabled()
                        ->default(fn () => MerchantOfferVoucher::generateCode())
                        ->helperText('Auto-generated')
                        ->maxLength(255),
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Voucher Code')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('merchant_offer_vouchers.code', strtoupper($search));
                    }),

                (!auth()->user()->hasRole('merchant')) ?  TextColumn::make('campaign.name')
                    ->label('Campaign')
                    ->url(fn ($record) => ($record->merchant_offer->campaign) ? route('filament.resources.merchant-offer-campaigns.edit', $record->merchant_offer->campaign) : null)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('campaign', function ($query) use ($search) {
                            $query->where('merchant_offer_campaigns.name', 'like', "%{$search}%");
                        });
                    }) : null,

                (!auth()->user()->hasRole('merchant')) ? TextColumn::make('merchant_offer.name')
                    ->label('Merchant Offer')
                    ->url(fn ($record) => route('filament.resources.merchant-offers.edit', $record->merchant_offer))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('merchant_offer', function ($query) use ($search) {
                            $query->where('merchant_offers.name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable() : null,

                // sku
                TextColumn::make('merchant_offer.sku')
                    ->label('SKU')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('merchant_offer', function ($query) use ($search) {
                            $query->where('merchant_offers.sku', 'like', "%{$search}%");
                        });
                    }),

                // financial status (claim status)
                Tables\Columns\BadgeColumn::make('latestSuccessfulClaim.status')
                    ->label('Financial Status')
                    ->default(0)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            MerchantOfferClaim::select('status')
                                ->whereColumn('voucher_id', 'merchant_offer_vouchers.id')
                                ->where('status', MerchantOfferClaim::CLAIM_SUCCESS)
                                ->latest('created_at')
                                ->limit(1),
                            $direction
                        );
                    })
                    ->enum([
                        0 => 'Unclaimed',
                        1 => MerchantOfferClaim::CLAIM_STATUS[1],
                        2 => MerchantOfferClaim::CLAIM_STATUS[2],
                        3 => MerchantOfferClaim::CLAIM_STATUS[3],
                    ])
                    ->colors([
                        'secondary' => 0,
                        'success' => 1,
                        'danger' => 2,
                        'warning' => 3,
                    ]),

                // redemptions status
                Tables\Columns\BadgeColumn::make('voucher_redeemed') // using append.
                    ->label('Redemption Status')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->leftJoin('merchant_offer_user', 'merchant_offer_vouchers.id', '=', 'merchant_offer_user.voucher_id')
                            ->leftJoin('merchant_offer_claims_redemptions', 'merchant_offer_user.id', '=', 'merchant_offer_claims_redemptions.claim_id')
                            ->orderByRaw("CASE WHEN merchant_offer_claims_redemptions.id IS NOT NULL THEN 1 ELSE 0 END {$direction}")
                            ->select('merchant_offer_vouchers.*');
                    })
                    ->enum([
                        false => 'Not Redeemed',
                        true => 'Redeemed'
                    ])
                    ->colors([
                        'secondary' => false,
                        'success' => true,
                    ]),

                Tables\Columns\TextColumn::make('owner.name')
                    ->label('Purchased By')
                    ->default('-')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('owner', function ($query) use ($search) {
                            $query->where('users.name', 'like', "%{$search}%");
                        });
                    }),

                Tables\Columns\TextColumn::make('latestSuccessfulClaim.purchase_method')
                    ->label('Purchase Method')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            MerchantOfferClaim::select('purchase_method')
                                ->whereColumn('voucher_id', 'merchant_offer_vouchers.id')
                                ->where('status', MerchantOfferClaim::CLAIM_SUCCESS)
                                ->latest('created_at')
                                ->limit(1),
                            $direction
                        );
                    })
                    ->formatStateUsing(function ($state) {
                        if ($state == 'fiat') {
                            return 'Cash';
                        } else if ($state == 'points') {
                            return 'Funhub';
                        } else {
                            return '-';
                        }
                    }),

                Tables\Columns\TextColumn::make('latestSuccessfulClaim.net_amount')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            MerchantOfferClaim::select('net_amount')
                                ->whereColumn('voucher_id', 'merchant_offer_vouchers.id')
                                ->where('status', MerchantOfferClaim::CLAIM_SUCCESS)
                                ->latest('created_at')
                                ->limit(1),
                            $direction
                        );
                    })
                    ->formatStateUsing(function ($state) {
                        if ($state) {
                            return number_format($state, 2);
                        } else {
                            return '-';
                        }
                    })
                    ->label('Amount'),

                Tables\Columns\TextColumn::make('latestSuccessfulClaim.created_at')
                    ->label('Purchased At')
                    ->date('d/m/Y h:ia')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            MerchantOfferClaim::select('created_at')
                                ->whereColumn('voucher_id', 'merchant_offer_vouchers.id')
                                ->where('status', MerchantOfferClaim::CLAIM_SUCCESS)
                                ->latest('created_at')
                                ->limit(1),
                            $direction
                        );
                    }),
                Tables\Columns\TextColumn::make('voided')
                    ->label('Voided')
                    ->formatStateUsing(function ($state) {
                        switch ($state) {
                            case 0:
                                return 'No';
                                break;
                            case 1:
                                return 'Yes';
                                break;
                        }
                    })
            ])
            ->filters([

                SelectFilter::make('campaign')
                ->label('Campaign')
                ->relationship('campaign', 'name', function ($query) {
                    return $query->whereHas('merchantOffers', function ($query) {
                        $query->whereHas('vouchers');
                    });
                })
                ->searchable(),

                SelectFilter::make('merchant_offer_id')
                    ->label('Merchant Offer')
                    ->relationship('merchant_offer', 'name')
                    ->searchable(),

                SelectFilter::make('claimStatus')
                    ->options([
                        'unclaimed' => 'Unclaimed',
                        1 => MerchantOfferClaim::CLAIM_STATUS[1],
                        2 => MerchantOfferClaim::CLAIM_STATUS[2],
                        3 => MerchantOfferClaim::CLAIM_STATUS[3],
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['value'] === 'unclaimed') {
                            // For unclaimed vouchers where there's no claim record
                            $query->whereNull('owned_by_id');
                        } else if ($data['value']) {
                            // For specific claim statuses
                            $query->whereHas('latestSuccessfulClaim', function ($q) use ($data) {
                                $q->where('status', $data['value']);
                            });
                        }
                    })
                    ->label('Financial Status'),

                SelectFilter::make('getVoucherRedeemedAttribute')
                    // ->relationship('redeem', 'id')
                    ->options([
                        false => 'Not Redeemed',
                        true => 'Redeemed'
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['value'] == false) {
                            // no filter
                        } else {
                            $query->whereHas('redeem');
                        }
                    })
                    ->label('Redemption Status'),
                SelectFilter::make('merchant_offer_id')
                    ->relationship('merchant_offer', 'name')
                    ->searchable()
                    ->label('Merchant Offer'),
                Filter::make('purchased_from')
                    ->form([
                        DatePicker::make('purchased_from')
                            ->placeholder('Select start date'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['purchased_from']) {
                            $query->whereHas('latestSuccessfulClaim', function ($q) use ($data) {
                                $q->whereDate('created_at', '>=', $data['purchased_from']);
                            });
                        }
                    })
                    ->label('Purchased From'),

                Filter::make('purchased_until')
                    ->form([
                        DatePicker::make('purchased_until')
                            ->placeholder('Select end date'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['purchased_until']) {
                            $query->whereHas('latestSuccessfulClaim', function ($q) use ($data) {
                                $q->whereDate('created_at', '<=', $data['purchased_until']);
                            });
                        }
                    })
                    ->label('Purchased Until'),
            ])
            ->actions([
                ViewAction::make(),

                // Void by Admin
                Action::make('voided')
                    ->label('Void')
                    // ->visible(fn () => auth()->user()->hasRole('super_admin'))
                    ->visible(function (Model $record) {
                        return auth()->user()->hasRole('super_admin') &&
                            $record->latestSuccessfulClaim &&
                            $record->latestSuccessfulClaim->status == MerchantOfferClaim::CLAIM_SUCCESS &&
                            !$record->voucher_redeemed;
                    })
                    ->action(function (Model $record) {
                        // Set 'voided' to true
                        $record->update([
                            'voided' => true,
                            'owned_by_id' => null,
                        ]);

                        MerchantOfferClaim::where('voucher_id', $record->id)
                            ->update([
                                // 'voucher_id' => null, // Update 'voucher_id' in merchant_offer_user table to null --> Financial status will revert back to false ('Not Redeemed'),
                                'status' => MerchantOfferClaim::CLAIM_FAILED, // Update claim_status in merchant_offer_user table to failed
                            ]);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Void Claimed Voucher')
                    ->modalSubheading('Are you sure you\'d like to void this claimed voucher? This cannot be undone.')
                    ->modalButton('Yes, void claimed voucher'),

                // Redeem by admin
                Action::make('Redeem')
                    ->label('Redeem')
                    ->visible(function (Model $record) {
                        return auth()->user()->hasRole('super_admin') &&
                            $record->latestSuccessfulClaim &&
                            $record->latestSuccessfulClaim->status == MerchantOfferClaim::CLAIM_SUCCESS &&
                            !$record->voucher_redeemed;
                    })
                    ->action(function (Model $record) {
                        $userId = $record->owner->id;
                        $offer = $record->merchant_offer;
                        $claim = MerchantOfferClaim::where('voucher_id', $record->id)
                            ->where('user_id', $userId)
                            ->first();

                        if ($offer && $claim) {
                            $offer->redeems()->attach($record->owner->id, [
                                'claim_id' => $claim->id,
                                'quantity' => 1,
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Voucher '.$record->code.' Redeemed')
                                ->send();
                        } else {
                            Notification::make()
                                ->danger()
                                ->title('Voucher '.$record->code.' Redeem Failed. Offer or claim record not valid.')
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Redeem Claimed Voucher')
                    ->modalSubheading('Are you sure you\'d like to redeem this claimed voucher? This cannot be undone. User cannot redeem the voucher again in merchant store.')
                    ->modalButton('Yes, Redeem Voucher'),

            ])
            ->bulkActions([
                ExportBulkAction::make()->exports([
                    ExcelExport::make('table')->fromTable(),
                ]),

                BulkAction::make('move')
                ->action(function (Collection $records, array $data): void {
                    $counter = 0;
                    foreach($records as $record) {
                        // move unclaimed vouchers from one merchant offer to another
                        if ($record->owned_by_id) { // skip those already claimed
                            continue;
                        }
                        $from = $record->merchant_offer_id;
                        $record->merchant_offer_id = $data['merchant_offer_id'];
                        $record->save();

                        // ensure move increase for to
                        MerchantOffer::where('id', $data['merchant_offer_id'])
                            ->increment('quantity', 1);

                        // count no. of quantity for from based on no. of vouchers unclaimed
                        $from_count = MerchantOfferVoucher::where('merchant_offer_id', $from)
                            ->whereNull('owned_by_id')
                            ->count();
                        // update from merchant offer quantity to from_count
                        MerchantOffer::where('id', $from)
                            ->update(['quantity' => $from_count]);

                        // create a new movement record
                        MerchantOfferVoucherMovement::create([
                            'from_merchant_offer_id' => $from,
                            'to_merchant_offer_id' => $data['merchant_offer_id'],
                            'voucher_id' => $record->id,
                            'user_id' => auth()->user()->id,
                            'remarks' => $data['remarks'],
                        ]);

                        // also update associated campaign if any schedule quantity:
                        $schedule_id = MerchantOffer::find($from);
                        $to_schedule_id = MerchantOffer::find($data['merchant_offer_id']);
                        $schedule_id = $schedule_id ? $schedule_id->schedule_id : null;
                        $to_schedule_id = $to_schedule_id ? $to_schedule_id->schedule_id : null;
                        Log::info('Schedule ID', [
                            'from_schedule' => $schedule_id,
                            'to_schedule' => $to_schedule_id,
                            'from_merchant_offer' => $from,
                            'to_merchant_offer' => $data['merchant_offer_id'],
                        ]);

                        if ($schedule_id && $to_schedule_id) {
                            MerchantOfferCampaignSchedule::where('id', $schedule_id)
                                ->update(['quantity' => $from_count]);

                            MerchantOfferCampaignSchedule::where('id', $to_schedule_id)
                                ->increment('quantity', 1);
                        }

                        Log::info('Voucher moved', [
                            'from' => $from,
                            'to' => $data['merchant_offer_id'],
                            'user_id' => auth()->user()->id,
                        ]);
                        $counter++;
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('Successfully Moved')
                        ->body('Total '.$counter.' voucher(s) has been to moved.')
                        ->success()
                        ->send();
                })
                ->requiresConfirmation()
                ->deselectRecordsAfterCompletion()
                ->form([
                    Select::make('merchant_offer_id')
                        ->relationship('merchant_offer', 'name')
                        ->getOptionLabelFromRecordUsing(fn (MerchantOffer $record) => $record->name . ' (SKU:'.$record->sku .')')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Textarea::make('remarks')
                        ->label('Remarks')
                        ->rows(2)
                        ->nullable(),
                ]),
            ]);
    }
    public static function getRelations(): array
    {
        return [
            MerchantOfferVouchersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMerchantOfferVouchers::route('/'),
            'create' => Pages\CreateMerchantOfferVoucher::route('/create'),
            'view' => Pages\ViewMerchantOffers::route('/{record}'),
            'edit' => Pages\EditMerchantOfferVoucher::route('/{record}/edit'),
        ];
    }
}
