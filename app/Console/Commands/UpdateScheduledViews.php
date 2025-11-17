<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use App\Models\Article;
use App\Models\Setting;
use App\Models\User;
use App\Models\ViewQueue;
use Illuminate\Support\Facades\Log;

class UpdateScheduledViews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:scheduled-views';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the scheduled views for articles based on interaction score';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $articleIds = ViewQueue::where('is_processed', false)
                            ->distinct('article_id')
                            ->pluck('article_id');

            foreach ($articleIds as $articleId) {
                $articleInteractionScore = 0;
                
                $article = Article::find($articleId);
                try {
                    if ($article) {
                        $articleInteractionScore = $article->calculateInteractionScore();
                    }
                    Log::info('[UpdateScheduledViews] Article Interaction Score: ', [
                        'article_id' => $articleId,
                        'article_interaction_score' => $articleInteractionScore,
                    ]);
                } catch (Exception $e) {
                    Log::error('[UpdateScheduledViews] Error calculating interaction score: ', [
                        'article_id' => $articleId,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }

                $pendingViewQueueRecords = ViewQueue::where('article_id', $articleId)
                                    ->where('is_processed', false)
                                    ->get();

                foreach ($pendingViewQueueRecords as $record) {
                    switch (true) {
                        case $articleInteractionScore === 0:
                            $scheduledViews = (int) round($record->scheduled_views * 0.2);
                            break;
                        case $articleInteractionScore >= 1 && $articleInteractionScore < 100:
                            $scheduledViews = (int) round($record->scheduled_views * 0.5);
                            break;
                        case $articleInteractionScore >= 100 && $articleInteractionScore < 200:
                            $scheduledViews = (int) round($record->scheduled_views * 0.75);
                            break;
                        default:
                            // No change on scheduled views
                            $scheduledViews = $record->scheduled_views;
                    }

                    if ($scheduledViews < 0) {
                        $scheduledViews = 0;
                    }

                    // Log the information for each record
                    Log::info('[UpdateScheduledViews]', [
                        'article_id' => $article->id,
                        'article_interaction_score' => $articleInteractionScore,
                        'scheduled_views_before' => $record->scheduled_views,
                        'scheduled_views_after' => $scheduledViews,
                    ]);
                
                    $record->update(['updated_scheduled_views' => $scheduledViews]);
                }

            }
        } catch (Exception $e) {
            Log::error('[UpdateScheduledViews] Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }


}
