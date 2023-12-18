<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\View;
use Illuminate\Console\Command;
use Algolia\AlgoliaSearch\InsightsClient;
use Illuminate\Support\Facades\Log;

class HistoricalArticleViewInsightImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:article-view-insights';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $insights;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // check if algolia is enabled
        if (!config('scout.algolia.id') || !config('scout.algolia.secret')) {
            return Command::FAILURE;
        }

        // check if scout package is installed
        if (!class_exists(InsightsClient::class)) {
            return Command::FAILURE;
        }

        $this->insights = InsightsClient::create(
            config('scout.algolia.id'),
            config('scout.algolia.secret')
        );

        // get numer of view records can be synced
        $totalViews = View::where('viewable_type', Article::class)
            ->where('is_system_generated', false)
            ->count();

        $this->info('Total Views Records to Sync: ' . $totalViews);

        // get views by batches
        $views = View::where('viewable_type', Article::class)
            ->where('is_system_generated', false)
            ->orderBy('id')
            ->chunk(500, function ($views) {
                $this->batchSendInsights($views);
                $views->each(function ($view) {
                    $this->info('Processing ID:' .$view->id);
                });
            });

        return Command::SUCCESS;
    }

    protected function batchSendInsights($views) {
        // map views to insights array
        $insightsArray = $views->map(function ($view) {
            return [
                'eventType' => 'click',
                'eventName' => 'Article Clicked',
                'index' => config('scout.prefix').'articles_index',
                'userToken' => (string) $view->user_id,
                'objectIDs' => [(string) $view->viewable_id],
            ];
        })->toArray();

        try {
            $response = $this->insights->sendEvents($insightsArray);
        } catch (\Exception $e) {
            Log::error('[HistoricalArticleViewInsightImport] Error sending events to Algolia', ['error' => $e->getMessage()]);
        }
    }
}
