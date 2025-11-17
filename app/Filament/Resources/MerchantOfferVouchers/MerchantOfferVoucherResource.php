<?php

namespace App\Filament\Resources\MerchantOfferVouchers;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use App\Filament\Resources\MerchantOfferVouchers\Pages\ListMerchantOfferVouchers;
use App\Filament\Resources\MerchantOfferVouchers\Pages\CreateMerchantOfferVoucher;
use App\Filament\Resources\MerchantOfferVouchers\Pages\ViewMerchantOffers;
use App\Filament\Resources\MerchantOfferVouchers\Pages\EditMerchantOfferVoucher;
use Filament\Forms;
use Filament\Tables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use App\Models\MerchantOfferClaim;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Log;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use Pages\ViewMerchantOfferVoucher;
use App\Models\MerchantOfferVoucher;
use Filament\Forms\Components\Select;
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
use App\Filament\Resources\MerchantOfferVouchers\Pages\MerchantOfferVouchersRelationManager;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferCampaignSchedule;
use App\Models\MerchantOfferVoucherMovement;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

class MerchantOfferVoucherResource extends Resource
{
    protected static ?string $model = MerchantOfferVoucher::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $modelLabel = 'Stock Voucher';

    protected static string | \UnitEnum | null $navigationGroup = 'Merchant Offers';

    protected static ?int $navigationSort = 3;

//    public static function getEloquentQuery(): Builder
//    {
//        $query = static::getModel()::query();
//        if (auth()->user()->hasRole('merchant')) {
//            // whereHas offer with user_id = auth()->id()
//            $query->whereHas('merchant_offer', function ($q) {
//                $q->where('user_id', auth()->id());
//            });
//        }
//
//        return $query;
//    }

	public static function getEloquentQuery(): Builder
	{
        $query = parent::getEloquentQuery()
        ->with([
            'latestSuccessfulClaim' => function ($query) {
                $query->select('id', 'voucher_id', 'status', 'purchase_method', 'net_amount', 'created_at')
                      ->where('status', 1); // Only load successful claims
            },
            'owner' => function ($query) {
                $query->select('id', 'name');
            },
            'merchant_offer' => function ($query) {
                $query->select('id', 'name', 'sku');
            },
            'redeem'
        ]);
        
        // // Add index hint for better performance when using MySQL
        // if (config('database.default') === 'mysql') {
        //     // Force the use of the primary key for faster searches
        //     $query->from(\DB::raw('merchant_offer_vouchers USE INDEX (PRIMARY)'));
        // }
        
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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make([
                    Select::make('merchant_offer_id')
                        ->label('Attached to Merchant Offer')
                        ->relationship('merchant_offer', 'name')
                        ->preload()
                        ->searchable(),

                    TextInput::make('code')
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
                TextColumn::make('code')
                    ->label('Voucher Code')
                    ->copyable()
                    ->searchable(),
                
                // imported voucher code
                TextColumn::make('imported_code')
                    ->label('Imported Code')
                    ->searchable(),

				(!auth()->user()->hasRole('merchant')) ?  TextColumn::make('campaign.name')
					->label('Campaign')
					->url(fn ($record) => ($record->merchant_offer->campaign) ? route('filament.admin.resources.merchant-offer-campaigns.edit', $record->merchant_offer->campaign) : null)
//                    ->searchable(query: function (Builder $query, string $search): Builder {
//                        return $query->whereHas('campaign', function ($query) use ($search) {
//                            $query->where('merchant_offer_campaigns.name', 'like', "%{$search}%");
//                        });
//                    })
					: null,

				(!auth()->user()->hasRole('merchant')) ? TextColumn::make('merchant_offer.name')
					->label('Merchant Offer')
					->url(fn ($record) => route('filament.admin.resources.merchant-offers.edit', $record->merchant_offer))
//                    ->searchable(query: function (Builder $query, string $search): Builder {
//                        return $query->whereHas('merchant_offer', function ($query) use ($search) {
//                            $query->where('merchant_offers.name', 'like', "%{$search}%");
//                        });
//                    })
					->sortable() : null,

				// sku
				TextColumn::make('merchant_offer.sku')
					->label('SKU'),
//                    ->searchable(query: function (Builder $query, string $search): Builder {
//                        return $query->whereHas('merchant_offer', function ($query) use ($search) {
//                            $query->where('merchant_offers.sku', 'like', "%{$search}%");
//                        });
//                    }),

                // financial status (claim status)
                TextColumn::make('latestSuccessfulClaim.status')
                    ->label('Financial Status')
                    ->default(0)
                    ->badge()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        // Optimize sorting with direct SQL subquery
                        return $query->orderByRaw("(SELECT COALESCE(
                            (SELECT status FROM merchant_offer_user 
                            WHERE merchant_offer_user.voucher_id = merchant_offer_vouchers.id 
                            AND status = 1 
                            ORDER BY created_at DESC LIMIT 1), 0)) {$direction}");
                    })
                    ->formatStateUsing(fn ($state) => match($state) {
                        0 => 'Unclaimed',
                        1 => MerchantOfferClaim::CLAIM_STATUS[1] ?? 'Claimed',
                        2 => MerchantOfferClaim::CLAIM_STATUS[2] ?? 'Failed',
                        3 => MerchantOfferClaim::CLAIM_STATUS[3] ?? 'Await Payment',
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        0 => 'secondary',
                        1 => 'success',
                        2 => 'danger',
                        3 => 'warning',
                        default => 'gray',
                    }),

                // redemptions status
                TextColumn::make('voucher_redeemed') // using append.
                    ->label('Redemption Status')
                    ->badge()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("(EXISTS (SELECT 1 FROM merchant_offer_user 
                            JOIN merchant_offer_claims_redemptions ON merchant_offer_user.id = merchant_offer_claims_redemptions.claim_id 
                            WHERE merchant_offer_user.voucher_id = merchant_offer_vouchers.id LIMIT 1)) {$direction}");
                    })
                    ->formatStateUsing(fn ($state) => match($state) {
                        false => 'Not Redeemed',
                        true => 'Redeemed',
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        false => 'secondary',
                        true => 'success',
                        default => 'gray',
                    }),

				TextColumn::make('owner.name')
					->label('Purchased By')
                    ->url(function ($record) {
                        if ($record->owner) {
                            return route('filament.admin.resources.users.view', $record->owner);
                        }
                        return '#';
                    })
					->default('-'),
//                    ->searchable(query: function (Builder $query, string $search): Builder {
//                        return $query->whereHas('owner', function ($query) use ($search) {
//                            $query->where('users.name', 'like', "%{$search}%");
//                        });
//                    }),

                // Tables\Columns\TextColumn::make('latestSuccessfulClaim.purchase_method')
                //     ->label('Purchase Method')
                //     ->sortable(query: function (Builder $query, string $direction): Builder {
                //         // Optimize sorting with a more efficient subquery approach
                //         return $query->orderByRaw(
                //             "(SELECT purchase_method FROM merchant_offer_user 
                //             WHERE merchant_offer_user.voucher_id = merchant_offer_vouchers.id 
                //             AND merchant_offer_user.status = ? 
                //             ORDER BY merchant_offer_user.created_at DESC LIMIT 1) $direction",
                //             [MerchantOfferClaim::CLAIM_SUCCESS]
                //         );
                //     })
                //     ->formatStateUsing(function ($state) {
                //         if ($state == 'fiat') {
                //             return 'Cash';
                //         } else if ($state == 'points') {
                //             return 'Funhub';
                //         } else {
                //             return '-';
                //         }
                //     }),

                // Tables\Columns\TextColumn::make('latestSuccessfulClaim.net_amount')
                //     ->sortable(query: function (Builder $query, string $direction): Builder {
                //         // Optimize sorting with a more efficient subquery approach
                //         return $query->orderByRaw(
                //             "(SELECT net_amount FROM merchant_offer_user 
                //             WHERE merchant_offer_user.voucher_id = merchant_offer_vouchers.id 
                //             AND merchant_offer_user.status = ? 
                //             ORDER BY merchant_offer_user.created_at DESC LIMIT 1) $direction",
                //             [MerchantOfferClaim::CLAIM_SUCCESS]
                //         );
                //     })
                //     ->formatStateUsing(function ($state) {
                //         if ($state) {
                //             return number_format($state, 2);
                //         } else {
                //             return '-';
                //         }
                //     })
                //     ->label('Amount'),

                TextColumn::make('latestSuccessfulClaim.created_at')
                    ->label('Purchased At')
                    ->date('d/m/Y h:ia')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("(SELECT COALESCE(
                            (SELECT created_at FROM merchant_offer_user 
                            WHERE merchant_offer_user.voucher_id = merchant_offer_vouchers.id 
                            AND status = 1 
                            ORDER BY created_at DESC LIMIT 1), '1970-01-01')) {$direction}");
                    }),
                TextColumn::make('voided')
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

                SelectFilter::make('financial_status')
                    ->options([
                        'unclaimed' => 'Unclaimed',
                        1 => MerchantOfferClaim::CLAIM_STATUS[1],
                        2 => MerchantOfferClaim::CLAIM_STATUS[2],
                        3 => MerchantOfferClaim::CLAIM_STATUS[3],
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!isset($data['value']) || $data['value'] === null) {
                            return $query;
                        }
                        
                        if ($data['value'] === 'unclaimed') {
							return $query->whereNotExists(function ($subquery) {
								$subquery->select(DB::raw(1))
									->from('merchant_offer_user')
									->whereColumn('merchant_offer_user.voucher_id', 'merchant_offer_vouchers.id')
									->where('merchant_offer_user.status', 1)
									->limit(1);
							});
                        } else if ($data['value']) {
							return $query->whereExists(function ($subquery) use ($data) {
								$subquery->select(DB::raw(1))
									->from('merchant_offer_user as mou1')
									->whereColumn('mou1.voucher_id', 'merchant_offer_vouchers.id')
									->where('mou1.status', $data['value'])
									->whereRaw('mou1.created_at = (
									SELECT MAX(mou2.created_at) 
									FROM merchant_offer_user as mou2 
									WHERE mou2.voucher_id = mou1.voucher_id
								)');
							});
                        }
                        
                        return $query;
                    })
                    ->label('Financial Status'),

                // Add optimized Purchased By filter
                SelectFilter::make('purchased_by')
                    ->label('Purchased By')
                    ->relationship('owner', 'name', function ($query) {
                        // Only include users who have purchased vouchers
                        return $query->whereIn('id', function ($subquery) {
                            $subquery->select('owned_by_id')
                                    ->from('merchant_offer_vouchers')
                                    ->whereNotNull('owned_by_id')
                                    ->distinct();
                        });
                    })
                    ->searchable(),
                    
                SelectFilter::make('redemption_status')
                    ->options([
                        false => 'Not Redeemed',
                        true => 'Redeemed'
                    ])
                    ->query(function (Builder $query, array $data) {
                        // If no value is selected, return the unmodified query
                        if (!isset($data['value']) || $data['value'] === null) {
                            return $query;
                        }
                        
                        if ($data['value'] == true) {
                            // Highly optimized query for redeemed vouchers
                            // Use a correlated subquery with EXISTS directly on redemptions table
                            return $query->whereExists(function ($subquery) {
                                $subquery->select(DB::raw(1))
                                    ->from('merchant_offer_claims_redemptions')
                                    ->join('merchant_offer_user', 'merchant_offer_claims_redemptions.claim_id', '=', 'merchant_offer_user.id')
                                    ->whereColumn('merchant_offer_user.voucher_id', 'merchant_offer_vouchers.id')
                                    ->limit(1);
                            });
                        } else if ($data['value'] == false) {
                            // Highly optimized query for unredeemed vouchers
                            // Use a correlated subquery with NOT EXISTS directly on redemptions table
                            return $query->whereNotExists(function ($subquery) {
                                $subquery->select(DB::raw(1))
                                    ->from('merchant_offer_claims_redemptions')
                                    ->join('merchant_offer_user', 'merchant_offer_claims_redemptions.claim_id', '=', 'merchant_offer_user.id')
                                    ->whereColumn('merchant_offer_user.voucher_id', 'merchant_offer_vouchers.id')
                                    ->limit(1);
                            });
                        }
                        
                        return $query;
                    })
                    ->label('Redemption Status'),
                SelectFilter::make('merchant_offer_id')
                    ->relationship('merchant_offer', 'name')
                    ->searchable()
                    ->label('Merchant Offer'),

                Filter::make('purchased_from')
                    ->schema([
                        DatePicker::make('purchased_from')
                            ->placeholder('Select start date'),
                        DatePicker::make('purchased_until')
                            ->placeholder('Select end date'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (isset($data['purchased_from']) && $data['purchased_from']) {
                            $query->whereHas('latestSuccessfulClaim', function ($q) use ($data) {
                                $q->whereDate('created_at', '>=', $data['purchased_from']);
                            });
                        }
                    })
                    ->label('Purchased From'),

                // Filter::make('purchased_until')
                //     ->form([
                //         DatePicker::make('purchased_until')
                //             ->placeholder('Select end date'),
                //     ])
                //     ->query(function (Builder $query, array $data) {
                //         if ($data['purchased_until']) {
                //             $query->whereHas('latestSuccessfulClaim', function ($q) use ($data) {
                //                 $q->whereDate('created_at', '<=', $data['purchased_until']);
                //             });
                //         }
                //     })
                //     ->label('Purchased Until'),
            ])
            ->recordActions([
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
            ->toolbarActions([
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

                    Notification::make()
                        ->title('Successfully Moved')
                        ->body('Total '.$counter.' voucher(s) has been to moved.')
                        ->success()
                        ->send();
                })
                ->requiresConfirmation()
                ->deselectRecordsAfterCompletion()
                ->schema([
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
            'index' => ListMerchantOfferVouchers::route('/'),
            'create' => CreateMerchantOfferVoucher::route('/create'),
            'view' => ViewMerchantOffers::route('/{record}'),
            'edit' => EditMerchantOfferVoucher::route('/{record}/edit'),
        ];
    }
}
