<?php

namespace App\Jobs;

use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IndexStore implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The store ID to index.
     *
     * @var int
     */
    protected $storeId;

    /**
     * Create a new job instance.
     *
     * @param int $storeId
     * @return void
     */
    public function __construct(int $storeId)
    {
        $this->storeId = $storeId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $store = Store::find($this->storeId);
            
            if (!$store) {
                Log::info('[IndexStore] Skipped indexing - store not found', [
                    'store_id' => $this->storeId
                ]);
                return;
            }
            
            // Make the store searchable (or update its searchable status)
            $store->searchable();
            
            Log::info('[IndexStore] Successfully indexed store', [
                'store_id' => $this->storeId,
                'ratings_avg' => $store->storeRatings->avg('rating')
            ]);
            
        } catch (\Exception $e) {
            Log::error('[IndexStore] Error indexing store', [
                'store_id' => $this->storeId,
                'error' => $e->getMessage()
            ]);
            
            // Re-throw the exception to trigger job retry
            throw $e;
        }
    }
}
