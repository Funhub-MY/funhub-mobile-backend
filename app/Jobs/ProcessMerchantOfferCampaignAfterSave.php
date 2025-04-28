<?php

namespace App\Jobs;

use App\Models\MerchantOffer;
use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferCampaignSchedule;
use App\Models\MerchantOfferVoucher;
use App\Jobs\SyncMerchantOfferStores;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMerchantOfferCampaignAfterSave implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	protected $record;

	/**
	 * Create a new job instance.
	 *
	 * @param MerchantOfferCampaign $record
	 * @return void
	 */
	public function __construct(MerchantOfferCampaign $record)
	{
		$this->record = $record;
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle()
	{
		Log::info("[Process Merchant offer campaign] Starting optimized processing", [
            'campaign_id' => $this->record->id,
            'campaign_name' => $this->record->name
        ]);

        // 1. Update basic offer information
        dispatch(new UpdateMerchantOfferBasicInfo($this->record));

        // 2. Handle media synchronization
        dispatch(new SyncMerchantOfferMedia($this->record));
        
        // 3. Sync offer categories
        dispatch(new SyncMerchantOfferCategories($this->record));
        
        // 4. Sync store mappings
        $campaignStoreIds = $this->record->stores->pluck('id')->toArray();
        dispatch(new SyncAllMerchantOfferStores($this->record->id, $campaignStoreIds));
        
        // 5. Process schedules and vouchers
        dispatch(new ProcessMerchantOfferSchedules($this->record));
        
        // 6. Finally, sync with Algolia once all other jobs are done
        dispatch(new SyncMerchantOfferToAlgolia($this->record->id))
            ->delay(now()->addMinutes(5)); // Allow other jobs to finish first
            
        Log::info("[Process Merchant offer campaign] All jobs dispatched", [
            'campaign_id' => $this->record->id
        ]);
	}
}