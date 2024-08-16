<?php

namespace App\Console\Commands;

use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // get all unonboarded stores
        $stores = Store::doesntHave('articles')
            ->doesntHave('user')
            ->where('status', Store::STATUS_ACTIVE)
            ->get();

        Log::info('[AutoHideUnonboardedStoresWithoutArticles] Total UnonboardedStores without articles: ' . $stores->count());
        $this->info('[AutoHideUnonboardedStoresWithoutArticles] Total UnonboardedStores without articles: ' . $stores->count());

        foreach ($stores as $store) {
            $store->status = Store::STATUS_INACTIVE;
            $store->save();

            Log::info('[AutoHideUnonboardedStoresWithoutArticles] Store ID: ' . $store->id . ' status changed to unlisted');
            $this->info('[AutoHideUnonboardedStoresWithoutArticles] Store ID: ' . $store->id . ' status changed to unlisted');
        }

        return Command::SUCCESS;
    }
}
