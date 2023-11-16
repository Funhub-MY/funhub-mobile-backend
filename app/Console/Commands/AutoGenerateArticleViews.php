<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\View;
use App\Models\ViewQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
    
            $viewQueueRecords = ViewQueue::where('scheduled_at', '<=', now())->get();
    
            if ($viewQueueRecords->isNotEmpty()) {
                foreach ($viewQueueRecords as $record) {
                    $articleId = $record->article_id;
                    $scheduledViews = $record->scheduled_views;
    
                    for ($i = 0; $i < $scheduledViews; $i++) {
                        View::create([
                            'user_id' => $this->getSuperAdminUserId(),
                            'viewable_type' => Article::class,
                            'viewable_id' => $articleId,
                            'ip_address' => null,
                            'is_system_generated' => true,
                        ]);
                    }

                    Log::info('[AutoGenerateArticleViews] Generated ', ['scheduled_views' => $scheduledViews, 'article_id' => $articleId]);
                }
            }
    
            Log::info('[AutoGenerateArticleViews] AutoGenerateArticleViews completed successfully');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            Log::error('[AutoGenerateArticleViews] Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
        // $viewQueueRecords = ViewQueue::where('scheduled_at', '<=', now())->get();

        // if ($viewQueueRecords) {
        //     foreach ($viewQueueRecords as $record) {
        //         $articleId = $record->article_id;
        //         $scheduledViews = $record->scheduled_views;
    
        //         for ($i = 0; $i < $scheduledViews; $i++) {
        //             View::create([
        //                 'user_id' => $this->getSuperAdminUserId(),
        //                 'viewable_type' => Article::class,
        //                 'viewable_id' => $articleId,
        //                 'ip_address' => null,
        //                 'is_system_generated' => true,
        //             ]);
        //         }
        //     }
        // }

        // return Command::SUCCESS;
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
