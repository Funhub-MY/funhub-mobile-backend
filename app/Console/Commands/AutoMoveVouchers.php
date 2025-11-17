<?php

namespace App\Console\Commands;

use Exception;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferVoucherMovement;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoMoveVouchers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'merchant-offers:auto-move-vouchers-unsold';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automactically move unsold vouchers';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // get all merchant offers published that has campaign_id
        $offers = MerchantOffer::has('campaign')
            ->where('status', MerchantOffer::STATUS_PUBLISHED)
            ->with('campaign')
            ->get();

        $this->info('[AutoMoveVouchers] Total Merchant Offers Found: ' . $offers->count());

        // go through each offer, check if the available_until is passed and still have unclaimedVouchers, move the unclaimed voucher to upcoming offer under same campaign id
        foreach ($offers as $offer) {
            $this->info('Checking: ' . $offer->id . ' ' . $offer->name . ' ' . $offer->available_at . '->' .$offer->available_until . ' - Unclaimed: ' . $offer->unclaimedVouchers()->count());
            // if no auto move vouchers turned on it will skip
            if ($offer->campaign->auto_move_vouchers == false) {
                $this->info('[AutoMoveVouchers] Auto Move Vouchers is turned off for this campaign, skipping');
                continue;
            }

            // already past available_until and still have unclaimed(unsold) vouchers
            // move to upcoming merchant offer under same campaign
            if (Carbon::parse($offer->available_until)->isPast() && $offer->unclaimedVouchers()->count() > 0) {
                $upcomingOffer = MerchantOffer::whereHas('campaign', function ($q) use ($offer) {
                    $q->where('merchant_offer_campaigns.id', $offer->campaign->id);
                })->where('id', '!=', $offer->id)
                    ->where('available_at', '>', now())
                    ->where('status', MerchantOffer::STATUS_PUBLISHED)
                    ->orderBy('available_at', 'asc')
                    ->first();

                if ($upcomingOffer) {
                    // Validate agreement_quantity if target campaign has it set
                    // Note: Moving within same campaign doesn't change total count, but we validate for safety
                    $targetCampaign = $upcomingOffer->campaign;
                    $vouchersToMove = $offer->unclaimedVouchers()->get();
                    $vouchersCount = $vouchersToMove->count();
                    
                    // If moving to a different campaign (shouldn't happen in automove, but validate anyway)
                    if ($targetCampaign && $targetCampaign->id !== $offer->campaign->id) {
                        if ($targetCampaign->agreement_quantity > 0) {
                            $targetCampaignVoucherCount = \App\Models\MerchantOfferVoucher::whereHas('merchant_offer', function ($query) use ($targetCampaign) {
                                $query->where('merchant_offer_campaign_id', $targetCampaign->id);
                            })->count();
                            
                            $maxAllowed = $targetCampaign->agreement_quantity - $targetCampaignVoucherCount;
                            
                            if ($maxAllowed < $vouchersCount) {
                                Log::warning('[AutoMoveVouchers] Cannot move all vouchers - target campaign agreement quantity limit', [
                                    'from_offer_id' => $offer->id,
                                    'to_offer_id' => $upcomingOffer->id,
                                    'target_campaign_id' => $targetCampaign->id,
                                    'agreement_quantity' => $targetCampaign->agreement_quantity,
                                    'current_vouchers' => $targetCampaignVoucherCount,
                                    'vouchers_to_move' => $vouchersCount,
                                    'max_allowed' => $maxAllowed,
                                ]);
                                $this->warn("[AutoMoveVouchers] Cannot move {$vouchersCount} vouchers - target campaign would exceed agreement quantity (max allowed: {$maxAllowed})");
                                return;
                            }
                        }
                    }
                    
                    DB::beginTransaction();
                    try {
                        $vouchersToMove->each(function ($voucher) use ($upcomingOffer, $offer) {

                            // create voucher movements
                            MerchantOfferVoucherMovement::create([
                                'from_merchant_offer_id' => $offer->id,
                                'to_merchant_offer_id' => $upcomingOffer->id,
                                'voucher_id' => $voucher->id,
                                'user_id' => $offer->user_id,
                                'remarks' => 'Auto Moved',
                            ]);

                            // moved voucher to upcoming offer
                            $voucher->update([
                                'merchant_offer_id' => $upcomingOffer->id
                            ]);

                            Log::info('[AutoMoveVouchers] Moved ', [
                                'voucher_id' => $voucher->id,
                                'code' => $voucher->code,
                                'from' => $offer->id,
                                'to' => $upcomingOffer->id
                            ]);

                            $this->info('[AutoMoveVouchers] Moved Voucher ID ' . $voucher->id . ' from ' . $offer->id . ' to ' . $upcomingOffer->id);
                        });

                        DB::commit();
                        $this->info('[AutoMoveVouchers] Successfully moved ' . $vouchersCount . ' vouchers from offer ' . $offer->id . ' to ' . $upcomingOffer->id);

                    } catch (Exception $e) {
                        DB::rollBack();
                        $this->error('[AutoMoveVouchers] Failed to move vouchers from offer ' . $offer->id . ': ' . $e->getMessage());
                        Log::error('[AutoMoveVouchers] Error moving vouchers', [
                            'from_offer_id' => $offer->id,
                            'to_offer_id' => $upcomingOffer->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                }

                // if dont have, no action, vouchers stock remained unsold.
            }
        }

        return Command::SUCCESS;
    }
}