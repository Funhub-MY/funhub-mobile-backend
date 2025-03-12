<?php

namespace App\Jobs;

use App\Models\MerchantOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IndexMerchantOffer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The merchant offer ID to index.
     *
     * @var int
     */
    protected $merchantOfferId;

    /**
     * Create a new job instance.
     *
     * @param int $merchantOfferId
     * @return void
     */
    public function __construct(int $merchantOfferId)
    {
        $this->merchantOfferId = $merchantOfferId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $offer = MerchantOffer::find($this->merchantOfferId);
            
            if ($offer && $offer->status === MerchantOffer::STATUS_PUBLISHED) {
                $offer->searchable();
                Log::info('[IndexMerchantOffer] Successfully indexed merchant offer', [
                    'offer_id' => $this->merchantOfferId
                ]);
            } else {
                Log::info('[IndexMerchantOffer] Skipped indexing - offer not found or not published', [
                    'offer_id' => $this->merchantOfferId
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[IndexMerchantOffer] Error indexing merchant offer', [
                'offer_id' => $this->merchantOfferId,
                'error' => $e->getMessage()
            ]);
            
            // Re-throw the exception to trigger job retry
            throw $e;
        }
    }
}
