<?php

namespace App\Filament\Resources\MerchantOffers\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\BulkAction;
use Exception;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferCampaignSchedule;
use App\Models\MerchantOfferClaim;
use App\Models\MerchantOfferClaimRedemptions;
use App\Models\MerchantOfferVoucher;
use App\Models\MerchantOfferVoucherMovement;
use App\Notifications\RedeemReview;
use Closure;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class VouchersRelationManager extends RelationManager
{
    protected static string $relationship = 'vouchers';

    protected static ?string $recordTitleAttribute = 'code';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->disabled()
                    ->default(fn () => MerchantOfferVoucher::generateCode())
                    ->helperText('Auto-generated')
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                ->label('Voucher Code')
                ->sortable()
                ->searchable(),

                TextColumn::make('imported_code')
                    ->label('Imported Code')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('latestSuccessfulClaim.status')
                    ->label('Financial Status')
                    ->default(0)
                    ->sortable()
                    ->badge()
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
                TextColumn::make('voucher_redeemed')
                    ->label('Redemption Status')
                    ->default(0)
                    ->badge()
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
                    ->default('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('latestSuccessfulClaim.purchase_method')
                    ->label('Purchase Method')
                    ->formatStateUsing(function ($state) {
                        if ($state == 'fiat') {
                            return 'Cash';
                        } else if ($state == 'points') {
                            return 'Funhub';
                        } else {
                            return '-';
                        }
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('latestSuccessfulClaim.net_amount')
                    ->formatStateUsing(function ($state) {
                        if ($state) {
                            return number_format($state, 2);
                        } else {
                            return '-';
                        }
                    })
                    ->label('Amount'),

                TextColumn::make('latestSuccessfulClaim.created_at')
                    ->label('Purchased At')
                    ->date('d/m/Y h:ia')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('latestSuccessfulClaim.status')
                    ->options(MerchantOfferClaim::CLAIM_STATUS)
                    ->label('Financial Status'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->after(function (MerchantOfferVoucher $record) {
                        // after created must increment merchant offer quantity
                        MerchantOffer::where('id', $record->merchant_offer_id)
                            ->increment('quantity', 1);
                    })
            ])
            ->recordActions([
                // Tables\Actions\EditAction::make(),
                // Tables\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                // Tables\Actions\DeleteBulkAction::make(),
                BulkAction::make('move')
                ->action(function (Collection $records, array $data): void {
                    $toOffer = \App\Models\MerchantOffer::find($data['merchant_offer_id']);
                    if (!$toOffer) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Target offer not found')
                            ->send();
                        return;
                    }
                    
                    // Validate agreement_quantity if moving to a different campaign
                    $toCampaign = $toOffer->campaign;
                    $vouchersToMove = $records->whereNull('owned_by_id');
                    $vouchersCount = $vouchersToMove->count();
                    
                    if ($toCampaign && $toCampaign->agreement_quantity > 0) {
                        // Get source offers to check if moving between campaigns
                        $fromOffers = $records->pluck('merchant_offer_id')->unique();
                        $fromCampaigns = \App\Models\MerchantOffer::whereIn('id', $fromOffers)
                            ->with('campaign')
                            ->get()
                            ->pluck('campaign.id')
                            ->unique();
                        
                        // If moving to a different campaign, validate agreement_quantity
                        if (!$fromCampaigns->contains($toCampaign->id)) {
                            $targetCampaignVoucherCount = \App\Models\MerchantOfferVoucher::whereHas('merchant_offer', function ($query) use ($toCampaign) {
                                $query->where('merchant_offer_campaign_id', $toCampaign->id);
                            })->count();
                            
                            $maxAllowed = $toCampaign->agreement_quantity - $targetCampaignVoucherCount;
                            
                            if ($maxAllowed < $vouchersCount) {
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title('Cannot move vouchers')
                                    ->body("Moving {$vouchersCount} vouchers would exceed target campaign agreement quantity (max allowed: {$maxAllowed})")
                                    ->send();
                                
                                \Illuminate\Support\Facades\Log::warning('[VouchersRelationManager] Cannot move vouchers - target campaign agreement quantity limit', [
                                    'to_campaign_id' => $toCampaign->id,
                                    'agreement_quantity' => $toCampaign->agreement_quantity,
                                    'current_vouchers' => $targetCampaignVoucherCount,
                                    'vouchers_to_move' => $vouchersCount,
                                    'max_allowed' => $maxAllowed,
                                ]);
                                return;
                            }
                        }
                    }
                    
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
                        ->searchable(['name', 'sku'])
                        //->preload()
                        ->required(),
                    Textarea::make('remarks')
                        ->label('Remarks')
                        ->rows(2)
                        ->nullable(),
                ]),
                BulkAction::make('sendRedeemReviewReminder')
                    ->label('Send Redeem Review Reminder')
                    ->icon('heroicon-o-bell')
                    ->action(function (Collection $records): void {
                        $sentCount = 0;
                        $failedCount = 0;

                        foreach ($records as $voucher) {
                            try {
                                // Check if the voucher has been redeemed
                                $redemption = MerchantOfferClaimRedemptions::where('claim_id', $voucher->claim->id)
                                    ->first();

                                if ($redemption) {
                                    // Clear the reminder_sent_at column
                                    $redemption->update(['reminder_sent_at' => null]);

                                    // Get the user and store information
                                    $user = $redemption->user;
                                    $store = $redemption->claim->merchantOffer->stores->first();

                                    // Log the data we're about to use
                                    Log::info('Preparing to send RedeemReview notification', [
                                        'user_id' => $user->id,
                                        'claim_id' => $redemption->claim->id,
                                        'store_id' => $store ? $store->id : null,
                                        'merchant_offer_id' => $redemption->claim->merchant_offer_id
                                    ]);

                                    // Send the notification
                                    $user->notify(new RedeemReview($redemption->claim, $user, $redemption->claim->merchantOffer, $redemption->claim->merchant_offer_id));
                                    $sentCount++;

                                    Log::info('RedeemReview notification sent successfully');
                                    $redemption->update(['reminder_sent_at' => now()]);

                                } else {
                                    Log::warning('Voucher not redeemed', ['voucher_id' => $voucher->id]);
                                    $failedCount++;
                                }
                            } catch (Exception $e) {
                                Log::error('Failed to send RedeemReview notification', [
                                    'voucher_id' => $voucher->id,
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString()
                                ]);
                                $failedCount++;
                            }
                        }

                        Notification::make()
                            ->title('Redeem Review Reminders Sent')
                            ->body("Successfully sent {$sentCount} reminders. Failed to send {$failedCount} reminders.")
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion()
                    ->requiresConfirmation()
            ]);
    }
}
