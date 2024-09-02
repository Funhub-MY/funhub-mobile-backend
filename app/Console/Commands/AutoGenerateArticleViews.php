<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\View;
use App\Models\ViewQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\Article;

class AutoGenerateArticleViews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:article-views';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process the ViewQueue and generate views for articles';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            Log::info('[AutoGenerateArticleViews] Running AutoGenerateArticleViews');

            $viewQueueRecords = ViewQueue::where('scheduled_at', '<=', now())
                                ->whereHas('article', function ($query) {
                                    $query->where('status', Article::STATUS_PUBLISHED)
                                        ->where('visibility', Article::VISIBILITY_PUBLIC);
                                })
                                ->where('is_processed', false)
                                ->get();

            $this->info('Total ViewQueue Records: ' . $viewQueueRecords->count());

            if ($viewQueueRecords->isNotEmpty()) {
                $batchSize = 250; // Batch size for inserting views
                $viewsData = [];
                $recordsToUpdate = [];

                $articleViews = [];
                foreach ($viewQueueRecords as $record) {
                    if (!$record) {
                        Log::error('[AutoGenerateArticleViews] ViewQueue record not found for id: ' . $record->id);
                        continue;
                    }

                    $articleId = $record->article_id;
                    $scheduledViews = $record->scheduled_views;

                    if (!isset($articleViews[$articleId])) {
                        $articleViews[$articleId] = 0;
                    }
                    $articleViews[$articleId] += $scheduledViews;

                    $recordsToUpdate[] = $record->id;
                }

                foreach ($articleViews as $articleId => $totalViews) {
                    for ($i = 0; $i < $totalViews; $i++) {
                        $viewsData[] = [
                            'user_id' => $this->getSuperAdminUserId(),
                            'viewable_type' => Article::class,
                            'viewable_id' => $articleId,
                            'ip_address' => null,
                            'is_system_generated' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        if (count($viewsData) === $batchSize) {
                            $this->info('Inserting ' . count($viewsData) . ' views for Article ID: ' . $articleId);
                            View::insert($viewsData);
                            $viewsData = []; // resets
                        }
                    }

                    if (!empty($viewsData)) { // remainder if there is
                        $this->info('Inserting ' . count($viewsData) . ' views for Article ID: ' . $articleId);
                        View::insert($viewsData);
                        $viewsData = [];
                    }

                    Log::info('[AutoGenerateArticleViews] Generated ', ['total_views' => $totalViews, 'article_id' => $articleId]);
                }

                if (!empty($recordsToUpdate)) {
                    ViewQueue::whereIn('id', $recordsToUpdate)->update(['is_processed' => true]);
                }
            }

            Log::info('[AutoGenerateArticleViews] AutoGenerateArticleViews completed successfully');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            Log::error('[AutoGenerateArticleViews] Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function getSuperAdminUserId() {
        $superAdminUser = User::whereHas('roles', function ($query) {
            $query->where('name', 'super_admin');
        })->first();

        if ($superAdminUser) {
            return $superAdminUser->id;
        }

        return null;
    }

        // protected function getRandomIpAddress() {
    //     return rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255);
    // }
}
