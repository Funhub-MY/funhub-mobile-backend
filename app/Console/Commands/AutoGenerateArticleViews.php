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
                                ->where('is_processed', false)
                                ->get();

            $this->info('Total ViewQueue Records: ' . $viewQueueRecords->count());

            if ($viewQueueRecords->isNotEmpty()) {
                // list all not processed records's article id
                $this->info('Not Processed ViewQueue Records: ' . $viewQueueRecords->where('is_processed', false)->pluck('article_id')->implode(','));

                $viewsData = [];
                $recordsToUpdate = [];

                $superAdminUserId = $this->getSuperAdminUserId();

                foreach ($viewQueueRecords as $record) {
                    $articleId = $record->article_id;
                    $scheduledViews = $record->scheduled_views;
                    if ($record->updated_scheduled_views) {
                        $scheduledViews = $record->updated_scheduled_views;
                    }

                    for ($i = 0; $i < $scheduledViews; $i++) {
                        $viewsData[] = [
                            'user_id' => $superAdminUserId,
                            'viewable_type' => Article::class,
                            'viewable_id' => $articleId,
                            'ip_address' => null,
                            'is_system_generated' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    $recordsToUpdate[] = $record->id;
                    $this->info('Generated ' . $scheduledViews . ' views for article id: ' . $articleId);
                    Log::info('[AutoGenerateArticleViews] Generated ', ['scheduled_views' => $scheduledViews, 'article_id' => $articleId]);
                }

                if (!empty($viewsData)) {
                    View::insert($viewsData);
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
