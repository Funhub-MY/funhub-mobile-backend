<?php

namespace App\Jobs;

use App\Models\MerchantOffer;
use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferCampaignSchedule;
use App\Models\MerchantOfferVoucher;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMerchantOfferSchedules implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $campaign;

    /**
     * Create a new job instance.
     *
     * @param MerchantOfferCampaign $campaign
     * @return void
     */
    public function __construct(MerchantOfferCampaign $campaign)
    {
        $this->campaign = $campaign;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("[ProcessMerchantOfferSchedules] Starting", [
            'campaign_id' => $this->campaign->id,
            'campaign_name' => $this->campaign->name,
            'schedule_count' => $this->campaign->schedules->count()
        ]);

        // First, archive offers that have been removed from schedules
        $this->archiveRemovedOffers();
        
        // Then process each schedule
        foreach ($this->campaign->schedules as $index => $schedule) {
            $this->processSchedule($schedule, $index);
        }

        Log::info("[ProcessMerchantOfferSchedules] Completed", [
            'campaign_id' => $this->campaign->id
        ]);
    }
    
    /**
     * Archive offers that have been removed from schedules
     */
    private function archiveRemovedOffers()
    {
        $offers = MerchantOffer::where('merchant_offer_campaign_id', $this->campaign->id)->get();
        $schedulesIds = $this->campaign->schedules->pluck('id');
        
        if ($offers->count() > $this->campaign->schedules->count()) {
            $offersToArchive = $offers->whereNotIn('schedule_id', $schedulesIds);
            
            foreach ($offersToArchive as $offer) {
                // Check if offer already has vouchers sold
                if ($offer->vouchers()->whereNotNull('owned_by_id')->count() > 0) {
                    Log::info('[ProcessMerchantOfferSchedules] Cannot archive offer as it has been sold', [
                        'offer_id' => $offer->id,
                        'schedule_id' => $offer->schedule_id,
                    ]);
                    continue;
                }

                $offer->delete(); // soft delete
                Log::info('[ProcessMerchantOfferSchedules] Archived offer as schedule is removed', [
                    'offer_id' => $offer->id,
                    'schedule_id' => $offer->schedule_id,
                ]);
            }
        }
    }
    
    /**
     * Process an individual schedule
     */
    private function processSchedule(MerchantOfferCampaignSchedule $schedule, int $index)
    {
        // Store the original status to preserve it if it was archived
        $originalStatus = $schedule->status;
        $wasArchived = $originalStatus === MerchantOfferCampaignSchedule::STATUS_ARCHIVED;

        // If schedule dates are in the past, don't update
        if (Carbon::now()->gte(Carbon::parse($schedule->available_until)) || 
            Carbon::now()->gte(Carbon::parse($schedule->available_at))) {
            
            Log::info('[ProcessMerchantOfferSchedules] Cannot update schedule as available_at/until is past', [
                'schedule_id' => $schedule->id,
                'available_until' => $schedule->available_until,
            ]);

            // If the schedule was archived, ensure it stays archived
            if ($wasArchived) {
                $schedule->update(['status' => MerchantOfferCampaignSchedule::STATUS_ARCHIVED]);
            }
            return;
        }

        // Find or create offer for this schedule
        $offer = MerchantOffer::where('schedule_id', $schedule->id)->first();

        if (!$offer) {
            // Create new offer for this schedule
            $this->createNewOfferForSchedule($schedule, $index);
        } else {
            // Update existing offer
            $this->updateExistingOffer($offer, $schedule, $wasArchived);
        }
    }
    
    /**
     * Create a new offer for a schedule
     */
    private function createNewOfferForSchedule(MerchantOfferCampaignSchedule $schedule, int $index)
    {
        Log::info('[ProcessMerchantOfferSchedules] Creating new offer for schedule', [
            'schedule_id' => $schedule->id,
        ]);

        // Create offer
        $offer = MerchantOffer::create([
            'user_id' => $this->campaign->user_id,
            'store_id' => $this->campaign->store_id ?? null,
            'merchant_offer_campaign_id' => $this->campaign->id,
            'schedule_id' => $schedule->id,
            'name' => $this->campaign->name,
            'highlight_messages' => $this->campaign->highlight_messages,
            'description' => $this->campaign->description,
            'available_for_web' => $this->campaign->available_for_web,
            'sku' => $this->campaign->sku . '-' . ($index + 1),
            'fine_print' => $this->campaign->fine_print,
            'redemption_policy' => $this->campaign->redemption_policy,
            'cancellation_policy' => $this->campaign->cancellation_policy,
            'publish_at' => $schedule->publish_at,
            'purchase_method' => $this->campaign->purchase_method,
            'unit_price' => $this->campaign->unit_price,
            'discounted_point_fiat_price' => $this->campaign->discounted_point_fiat_price,
            'point_fiat_price' => $this->campaign->point_fiat_price,
            'discounted_fiat_price' => $this->campaign->discounted_fiat_price,
            'fiat_price' => $this->campaign->fiat_price,
            'expiry_days' => ($schedule->expiry_days ?? $this->campaign->expiry_days),
            'available_at' => $schedule->available_at,
            'available_until' => $schedule->available_until,
            'quantity' => $schedule->quantity,
            'status' => $schedule->status,
        ]);

        // Create vouchers for the offer
        $this->createVouchersForOffer($offer, $schedule->quantity);
        
        // Dispatch jobs to handle media and categories for the new offer
        dispatch(new SyncMerchantOfferMedia($this->campaign));
        dispatch(new SyncMerchantOfferCategories($this->campaign));
        
        // Sync stores for the new offer
        $campaignStoreIds = $this->campaign->stores->pluck('id')->toArray();
        SyncMerchantOfferStores::dispatch($offer->id, $campaignStoreIds);
    }
    
    /**
     * Update an existing offer for a schedule
     */
    private function updateExistingOffer(MerchantOffer $offer, MerchantOfferCampaignSchedule $schedule, bool $wasArchived)
    {
        // Auto publish if available_at is less than or equal to current time
        $status = Carbon::parse($schedule->available_at)->lte(Carbon::now())
            ? MerchantOffer::STATUS_PUBLISHED
            : MerchantOffer::STATUS_DRAFT;

        // Check if the offer is already archived
        $isOfferArchived = $offer->status === MerchantOffer::STATUS_ARCHIVED;

        $offer->update([
            'available_at' => $schedule->available_at,
            'available_until' => $schedule->available_until,
            'status' => $isOfferArchived ? MerchantOffer::STATUS_ARCHIVED : $status,
            'publish_at' => $schedule->publish_at,
        ]);

        // If the schedule was archived, ensure it stays archived
        if ($wasArchived) {
            $schedule->update(['status' => MerchantOfferCampaignSchedule::STATUS_ARCHIVED]);
        }

        // Update vouchers to match quantity
        $this->updateVouchersForOffer($offer, $schedule);
    }
    
    /**
     * Create vouchers for a new offer
     */
    private function createVouchersForOffer(MerchantOffer $offer, int $quantity)
    {
        for ($i = 0; $i < $quantity; $i++) {
            MerchantOfferVoucher::create([
                'merchant_offer_id' => $offer->id,
                'code' => MerchantOfferVoucher::generateCode(),
            ]);
        }
        
        Log::info('[ProcessMerchantOfferSchedules] Created vouchers for new offer', [
            'offer_id' => $offer->id,
            'quantity' => $quantity,
        ]);
    }
    
    /**
     * Update vouchers for an existing offer
     */
    private function updateVouchersForOffer(MerchantOffer $offer, MerchantOfferCampaignSchedule $schedule)
    {
        $existingVouchers = $offer->vouchers()->count();

        // If schedule quantity > existing vouchers, create new vouchers
        if ($schedule->quantity > $existingVouchers) {
            $diff = $schedule->quantity - $existingVouchers;
            for ($i = 0; $i < $diff; $i++) {
                MerchantOfferVoucher::create([
                    'merchant_offer_id' => $offer->id,
                    'code' => MerchantOfferVoucher::generateCode(),
                ]);
            }

            Log::info('[ProcessMerchantOfferSchedules] Created new vouchers as adjusted in schedule', [
                'offer_id' => $offer->id,
                'schedule_id' => $schedule->id,
                'quantity_added' => $diff,
            ]);
        } 
        // If schedule quantity < existing vouchers, remove unclaimed vouchers
        else if ($schedule->quantity < $existingVouchers) {
            $diff = $existingVouchers - $schedule->quantity;
            $vouchersToDelete = $offer->vouchers()->whereNull('owned_by_id')->limit($diff)->get();

            Log::info('[ProcessMerchantOfferSchedules] Deleting unclaimed vouchers as adjusted in schedule', [
                'offer_id' => $offer->id,
                'schedule_id' => $schedule->id,
                'quantity_to_delete' => $diff,
                'vouchers_found' => $vouchersToDelete->count(),
            ]);

            // Delete unclaimed vouchers
            $offer->vouchers()->whereNull('owned_by_id')->limit($diff)->delete();
        }

        // Update final quantities
        $actualVoucherCount = $offer->vouchers()->count();
        $unclaimedVoucherCount = $offer->unclaimedVouchers()->count();
        
        $schedule->update([
            'quantity' => $actualVoucherCount,
        ]);
        
        $offer->update([
            'quantity' => $unclaimedVoucherCount,
        ]);
        
        Log::info('[ProcessMerchantOfferSchedules] Updated offer and schedule quantities', [
            'offer_id' => $offer->id,
            'schedule_id' => $schedule->id,
            'total_vouchers' => $actualVoucherCount,
            'unclaimed_vouchers' => $unclaimedVoucherCount,
        ]);
    }
}
