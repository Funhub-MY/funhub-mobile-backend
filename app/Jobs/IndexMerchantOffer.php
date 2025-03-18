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
            
            if (!$offer) {
                Log::info('[IndexMerchantOffer] Skipped indexing - offer not found', [
                    'offer_id' => $this->merchantOfferId
                ]);
                return;
            }
            
            // Handle both published and archived statuses
            if ($offer->status === MerchantOffer::STATUS_PUBLISHED || 
                $offer->status === MerchantOffer::STATUS_ARCHIVED) {
                
                // Make the offer searchable (or update its searchable status)
                $offer->searchable();
                
                Log::info('[IndexMerchantOffer] Successfully indexed merchant offer', [
                    'offer_id' => $this->merchantOfferId,
                    'status' => $offer->status
                ]);
            } else {
                // For other statuses like DRAFT, we might want to remove from index
                // If the offer was previously indexed but is now in a non-searchable state
                if (method_exists($offer, 'unsearchable')) {
                    $offer->unsearchable();
                    Log::info('[IndexMerchantOffer] Made offer unsearchable', [
                        'offer_id' => $this->merchantOfferId,
                        'status' => $offer->status
                    ]);
                } else {
                    Log::info('[IndexMerchantOffer] Skipped indexing - offer has non-indexable status', [
                        'offer_id' => $this->merchantOfferId,
                        'status' => $offer->status
                    ]);
                }
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
