<?php

namespace App\Services;

use App\Models\MerchantOffer;
use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferCampaignSchedule;
use App\Models\MerchantOfferVoucher;
use App\Models\Merchant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Synchronous Merchant Campaign Processor
 * 
 * Processes merchant campaigns synchronously with full transaction protection
 * to ensure data consistency. All critical operations (offers, vouchers) are
 * wrapped in transactions. Non-critical operations (Algolia sync) can be async.
 */
class MerchantCampaignProcessor
{
    /**
     * Process a campaign synchronously with transaction protection
     * 
     * @param MerchantOfferCampaign $campaign
     * @return array Processing results
     */
    public function processCampaign(MerchantOfferCampaign $campaign): array
    {
        $results = [
            'campaign_id' => $campaign->id,
            'offers_created' => 0,
            'offers_updated' => 0,
            'basic_info_updates' => 0,
            'vouchers_created' => 0,
            'vouchers_deleted' => 0,
            'errors' => [],
            'started_at' => now(),
        ];

        try {
            // Wrap everything in a transaction for consistency
            DB::transaction(function () use ($campaign, &$results) {
                // 1. Update basic offer information for all existing offers
                $this->updateExistingOffersBasicInfo($campaign, $results);
                
                // 2. Archive removed offers (schedules that no longer exist)
                $this->archiveRemovedOffers($campaign, $results);
                
                // 3. Process each schedule synchronously
                foreach ($campaign->schedules()->orderBy('available_at')->get() as $index => $schedule) {
                    try {
                        $this->processSchedule($campaign, $schedule, $index, $results);
                    } catch (Exception $e) {
                        // Log error but continue with other schedules
                        $results['errors'][] = [
                            'schedule_id' => $schedule->id,
                            'schedule_name' => $schedule->available_at . ' - ' . $schedule->available_until,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ];
                        
                        Log::error('[MerchantCampaignProcessor] Error processing schedule', [
                            'campaign_id' => $campaign->id,
                            'schedule_id' => $schedule->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
            
            // 3. Sync non-critical operations (can be async, but do it here for now)
            // These don't need to be in the transaction
            $this->syncNonCriticalOperations($campaign);
            
            $results['completed_at'] = now();
            $results['duration_seconds'] = $results['started_at']->diffInSeconds($results['completed_at']);
            $results['success'] = empty($results['errors']);
            
            Log::info('[MerchantCampaignProcessor] Campaign processing completed', $results);
            
        } catch (Exception $e) {
            // Transaction was rolled back, nothing was saved
            $results['errors'][] = [
                'type' => 'transaction_failure',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];
            
            $results['success'] = false;
            $results['completed_at'] = now();
            
            Log::error('[MerchantCampaignProcessor] Transaction failed', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);
        }
        
        return $results;
    }
    
    /**
     * Update basic information for all existing offers in the campaign
     */
    private function updateExistingOffersBasicInfo(MerchantOfferCampaign $campaign, array &$results): void
    {
        $offers = MerchantOffer::where('merchant_offer_campaign_id', $campaign->id)->get();
        
        foreach ($offers as $offer) {
            // Preserve archived status
            $isArchived = $offer->status === MerchantOffer::STATUS_ARCHIVED;
            
            $offer->update([
                'name' => $campaign->name,
                'highlight_messages' => $campaign->highlight_messages,
                'description' => $campaign->description,
                'fine_print' => $campaign->fine_print,
                'available_for_web' => $campaign->available_for_web,
                'redemption_policy' => $campaign->redemption_policy,
                'cancellation_policy' => $campaign->cancellation_policy,
                'purchase_method' => $campaign->purchase_method,
                'unit_price' => $campaign->unit_price,
                'discounted_point_fiat_price' => $campaign->discounted_point_fiat_price,
                'point_fiat_price' => $campaign->point_fiat_price,
                'discounted_fiat_price' => $campaign->discounted_fiat_price,
                'fiat_price' => $campaign->fiat_price,
                'expiry_days' => $campaign->expiry_days,
            ]);
            
            // Restore archived status if it was archived
            if ($isArchived) {
                $offer->update(['status' => MerchantOffer::STATUS_ARCHIVED]);
            }
        }
        
        // Track basic info updates (these are separate from schedule-based updates)
        $results['basic_info_updates'] = $offers->count();
        
        Log::info('[MerchantCampaignProcessor] Updated basic info for existing offers', [
            'campaign_id' => $campaign->id,
            'offers_updated' => $offers->count(),
        ]);
    }
    
    /**
     * Archive offers that have been removed from schedules
     */
    private function archiveRemovedOffers(MerchantOfferCampaign $campaign, array &$results): void
    {
        $offers = MerchantOffer::where('merchant_offer_campaign_id', $campaign->id)->get();
        $scheduleIds = $campaign->schedules()->pluck('id');
        
        $offersToArchive = $offers->whereNotIn('schedule_id', $scheduleIds);
        
        foreach ($offersToArchive as $offer) {
            // Don't archive if vouchers have been sold
            if ($offer->vouchers()->whereNotNull('owned_by_id')->count() > 0) {
                Log::info('[MerchantCampaignProcessor] Cannot archive offer with sold vouchers', [
                    'offer_id' => $offer->id,
                ]);
                continue;
            }
            
            $offer->delete(); // soft delete
            Log::info('[MerchantCampaignProcessor] Archived offer', [
                'offer_id' => $offer->id,
                'schedule_id' => $offer->schedule_id,
            ]);
        }
    }
    
    /**
     * Process a single schedule (idempotent)
     */
    private function processSchedule(
        MerchantOfferCampaign $campaign,
        MerchantOfferCampaignSchedule $schedule,
        int $index,
        array &$results
    ): void {
        // Skip past schedules
        if (Carbon::now()->gte(Carbon::parse($schedule->available_until))) {
            Log::info('[MerchantCampaignProcessor] Skipping past schedule', [
                'schedule_id' => $schedule->id,
                'available_until' => $schedule->available_until,
            ]);
            return;
        }
        
        // Check if offer already exists (idempotency)
        $offer = MerchantOffer::where('schedule_id', $schedule->id)->first();
        
        if (!$offer) {
            // Create new offer
            $this->createOfferForSchedule($campaign, $schedule, $index, $results);
        } else {
            // Update existing offer
            $this->updateOfferForSchedule($offer, $schedule, $results);
        }
    }
    
    /**
     * Create a new offer for a schedule
     */
    private function createOfferForSchedule(
        MerchantOfferCampaign $campaign,
        MerchantOfferCampaignSchedule $schedule,
        int $index,
        array &$results
    ): void {
        Log::info('[MerchantCampaignProcessor] Creating offer for schedule', [
            'campaign_id' => $campaign->id,
            'schedule_id' => $schedule->id,
        ]);
        
        // Get merchant_id from campaign (preferred) or derive from user_id
        $merchantId = $campaign->merchant_id;
        if (!$merchantId && $campaign->user_id) {
            $merchant = Merchant::where('user_id', $campaign->user_id)->first();
            $merchantId = $merchant ? $merchant->id : null;
        }
        
        // Create offer
        $offer = MerchantOffer::create([
            'user_id' => $campaign->user_id, // Keep for backward compatibility
            'merchant_id' => $merchantId, // Direct relationship
            'store_id' => $campaign->store_id ?? null,
            'merchant_offer_campaign_id' => $campaign->id,
            'schedule_id' => $schedule->id,
            'name' => $campaign->name,
            'highlight_messages' => $campaign->highlight_messages,
            'description' => $campaign->description,
            'available_for_web' => $campaign->available_for_web,
            'sku' => $campaign->sku . '-' . ($index + 1),
            'fine_print' => $campaign->fine_print,
            'redemption_policy' => $campaign->redemption_policy,
            'cancellation_policy' => $campaign->cancellation_policy,
            'publish_at' => $schedule->publish_at,
            'purchase_method' => $campaign->purchase_method,
            'unit_price' => $campaign->unit_price,
            'discounted_point_fiat_price' => $campaign->discounted_point_fiat_price,
            'point_fiat_price' => $campaign->point_fiat_price,
            'discounted_fiat_price' => $campaign->discounted_fiat_price,
            'fiat_price' => $campaign->fiat_price,
            'expiry_days' => ($schedule->expiry_days ?? $campaign->expiry_days),
            'available_at' => $schedule->available_at,
            'available_until' => $schedule->available_until,
            'quantity' => $schedule->quantity,
            'status' => $schedule->status,
        ]);
        
        // Create vouchers for the offer
        $vouchersCreated = $this->createVouchersForOffer($offer, $schedule->quantity);
        
        $results['offers_created']++;
        $results['vouchers_created'] += $vouchersCreated;
        
        Log::info('[MerchantCampaignProcessor] Created offer and vouchers', [
            'offer_id' => $offer->id,
            'vouchers_created' => $vouchersCreated,
        ]);
    }
    
    /**
     * Update an existing offer for a schedule
     */
    private function updateOfferForSchedule(
        MerchantOffer $offer,
        MerchantOfferCampaignSchedule $schedule,
        array &$results
    ): void {
        Log::info('[MerchantCampaignProcessor] Updating offer for schedule', [
            'offer_id' => $offer->id,
            'schedule_id' => $schedule->id,
        ]);
        
        // Auto publish if available_at is less than or equal to current time
        $status = Carbon::parse($schedule->available_at)->lte(Carbon::now())
            ? MerchantOffer::STATUS_PUBLISHED
            : MerchantOffer::STATUS_DRAFT;
        
        // Preserve archived status
        if ($offer->status === MerchantOffer::STATUS_ARCHIVED) {
            $status = MerchantOffer::STATUS_ARCHIVED;
        }
        
        $offer->update([
            'available_at' => $schedule->available_at,
            'available_until' => $schedule->available_until,
            'status' => $status,
            'publish_at' => $schedule->publish_at,
        ]);
        
        // Update vouchers to match quantity
        $voucherChanges = $this->updateVouchersForOffer($offer, $schedule);
        
        $results['offers_updated']++;
        $results['vouchers_created'] += $voucherChanges['created'];
        $results['vouchers_deleted'] += $voucherChanges['deleted'];
    }
    
    /**
     * Create vouchers for an offer with agreement_quantity validation
     */
    private function createVouchersForOffer(MerchantOffer $offer, int $quantity): int
    {
        $campaign = $offer->campaign;
        
        // Validate against agreement_quantity if set
        if ($campaign && $campaign->agreement_quantity > 0) {
            $currentVoucherCount = MerchantOfferVoucher::whereHas('merchant_offer', function ($query) use ($campaign) {
                $query->where('merchant_offer_campaign_id', $campaign->id);
            })->count();
            
            $maxAllowed = $campaign->agreement_quantity - $currentVoucherCount;
            $quantity = min($quantity, $maxAllowed);
            
            if ($quantity <= 0) {
                Log::warning('[MerchantCampaignProcessor] Cannot create vouchers - agreement quantity reached', [
                    'campaign_id' => $campaign->id,
                    'agreement_quantity' => $campaign->agreement_quantity,
                    'current_vouchers' => $currentVoucherCount,
                    'offer_id' => $offer->id,
                ]);
                return 0;
            }
        }
        
        // Process vouchers in chunks
        $chunkSize = 500;
        $totalCreated = 0;
        $now = now();
        
        for ($chunk = 0; $chunk < $quantity; $chunk += $chunkSize) {
            $chunkQuantity = min($chunkSize, $quantity - $chunk);
            $voucherData = [];
            
            for ($i = 0; $i < $chunkQuantity; $i++) {
                $voucherData[] = [
                    'merchant_offer_id' => $offer->id,
                    'code' => MerchantOfferVoucher::generateCode(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            
            if (!empty($voucherData)) {
                MerchantOfferVoucher::insert($voucherData);
                $totalCreated += count($voucherData);
            }
        }
        
        return $totalCreated;
    }
    
    /**
     * Update vouchers for an existing offer
     */
    private function updateVouchersForOffer(
        MerchantOffer $offer,
        MerchantOfferCampaignSchedule $schedule
    ): array {
        $existingVouchers = $offer->vouchers()->count();
        $changes = ['created' => 0, 'deleted' => 0];
        
        // If schedule quantity > existing vouchers, create new vouchers
        if ($schedule->quantity > $existingVouchers) {
            $diff = $schedule->quantity - $existingVouchers;
            $created = $this->createVouchersForOffer($offer, $diff);
            $changes['created'] = $created;
        } 
        // If schedule quantity < existing vouchers, delete unclaimed vouchers
        else if ($schedule->quantity < $existingVouchers) {
            $diff = $existingVouchers - $schedule->quantity;
            
            $chunkSize = 500;
            $totalDeleted = 0;
            
            while ($totalDeleted < $diff) {
                $remaining = $diff - $totalDeleted;
                $deleteCount = min($chunkSize, $remaining);
                
                $deleted = $offer->vouchers()
                    ->whereNull('owned_by_id')
                    ->limit($deleteCount)
                    ->delete();
                
                $totalDeleted += $deleted;
                
                if ($deleted == 0) {
                    break;
                }
            }
            
            $changes['deleted'] = $totalDeleted;
        }
        
        // Update offer quantity
        $unclaimedCount = $offer->vouchers()->whereNull('owned_by_id')->count();
        $offer->update(['quantity' => $unclaimedCount]);
        
        return $changes;
    }
    
    /**
     * Sync non-critical operations (media, categories, stores, Algolia)
     * These can be async but we do them here for simplicity
     */
    private function syncNonCriticalOperations(MerchantOfferCampaign $campaign): void
    {
        // These operations don't need to be in the transaction
        // They can fail without affecting data consistency
        
        try {
            // Sync media (can be async)
            $this->syncMedia($campaign);
            
            // Sync categories (can be async)
            $this->syncCategories($campaign);
            
            // Sync stores (can be async)
            $this->syncStores($campaign);
            
            // Algolia sync (should be async, but doing here for now)
            // dispatch(new SyncMerchantOfferToAlgolia($campaign->id))->delay(now()->addSeconds(30));
            
        } catch (Exception $e) {
            // Log but don't fail the whole operation
            Log::warning('[MerchantCampaignProcessor] Error syncing non-critical operations', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    private function syncMedia(MerchantOfferCampaign $campaign): void
    {
        $offers = MerchantOffer::where('merchant_offer_campaign_id', $campaign->id)->get();
        
        foreach ($offers as $offer) {
            // Copy gallery media
            $mediaItems = $campaign->getMedia(MerchantOfferCampaign::MEDIA_COLLECTION_NAME);
            foreach ($mediaItems as $mediaItem) {
                if (!$offer->getMedia(MerchantOffer::MEDIA_COLLECTION_NAME)->contains('id', $mediaItem->id)) {
                    $mediaItem->copy($offer, MerchantOffer::MEDIA_COLLECTION_NAME);
                }
            }
            
            // Copy horizontal banner media
            $mediaItems = $campaign->getMedia(MerchantOfferCampaign::MEDIA_COLLECTION_HORIZONTAL_BANNER);
            foreach ($mediaItems as $mediaItem) {
                if (!$offer->getMedia(MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER)->contains('id', $mediaItem->id)) {
                    $mediaItem->copy($offer, MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER);
                }
            }
        }
    }
    
    private function syncCategories(MerchantOfferCampaign $campaign): void
    {
        $offers = MerchantOffer::where('merchant_offer_campaign_id', $campaign->id)->get();
        $categoryIds = $campaign->allOfferCategories->pluck('id');
        
        foreach ($offers as $offer) {
            $offer->allOfferCategories()->sync($categoryIds);
        }
    }
    
    private function syncStores(MerchantOfferCampaign $campaign): void
    {
        $offers = MerchantOffer::where('merchant_offer_campaign_id', $campaign->id)->get();
        $storeIds = $campaign->stores->pluck('id');
        
        foreach ($offers as $offer) {
            $offer->stores()->sync($storeIds);
        }
    }
}

