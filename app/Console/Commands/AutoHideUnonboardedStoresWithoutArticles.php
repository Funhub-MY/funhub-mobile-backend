<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Models\Location;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\Article;

class AutoHideUnonboardedStoresWithoutArticles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stores:auto-hide-unonboarded';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stores auto hide unonboarded stores without articles';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $batchSize = 100;
        $totalProcessed = 0;

        $storeIds = Store::query()
            ->select('stores.id')
            ->doesntHave('user')
            ->where('stores.status', Store::STATUS_ACTIVE)
            ->whereHas('location', function($query) {
                $query->whereDoesntHave('articles', function($query) {
                    $query->where('status', Article::STATUS_PUBLISHED)
                          ->where('visibility', Article::VISIBILITY_PUBLIC);
                });
            })
            ->pluck('id');

        $totalToArchive = $storeIds->count();
        
        Log::info("[AutoHideUnonboardedStoresWithoutArticles] Found {$totalToArchive} stores to archive");
        $this->info("[AutoHideUnonboardedStoresWithoutArticles] Found {$totalToArchive} stores to archive");

        // Process stores in chunks to update their status
        foreach ($storeIds->chunk($batchSize) as $chunk) {
            Store::whereIn('id', $chunk)
                ->update(['status' => Store::STATUS_INACTIVE]);

            $processedCount = count($chunk);
            $totalProcessed += $processedCount;
            
            Log::info("[AutoHideUnonboardedStoresWithoutArticles] Processed {$processedCount} stores. Total progress: {$totalProcessed}/{$totalToArchive}");
            $this->info("[AutoHideUnonboardedStoresWithoutArticles] Processed {$processedCount} stores. Total progress: {$totalProcessed}/{$totalToArchive}");
        }

        Log::info("[AutoHideUnonboardedStoresWithoutArticles] Completed. Total stores archived: {$totalProcessed}");
        $this->info("[AutoHideUnonboardedStoresWithoutArticles] Completed. Total stores archived: {$totalProcessed}");

        return Command::SUCCESS;
    }
}
