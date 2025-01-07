<?php

namespace App\Console\Commands;

use App\Jobs\ByteplusVODProcess;
use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Command to process video articles from article_recommendations that do not have video jobs
 * So it can generate ABR stream link with Byteplus vod
 */
class ProcessPendingVideoArticlesToVOD extends Command
{
    protected $signature = 'app:process-pending-video-articles 
                            {--from= : Optional start date (Y-m-d)}
                            {--to= : Optional end date (Y-m-d)}';
                            
    protected $description = 'Process video articles from article_recommendations that do not have video jobs';

    public function handle()
    {
        $fromDate = $this->option('from');
        $toDate = $this->option('to');

        if ($fromDate && !$toDate) {
            $this->error('If from date is specified, to date is required');
            return 1;
        }

        if ($toDate && !$fromDate) {
            $this->error('If to date is specified, from date is required');
            return 1;
        }

        if ($fromDate && $toDate) {
            try {
                $fromDate = Carbon::createFromFormat('Y-m-d', $fromDate)->startOfDay();
                $toDate = Carbon::createFromFormat('Y-m-d', $toDate)->endOfDay();
                
                if ($fromDate->gt($toDate)) {
                    $this->error('From date cannot be greater than to date');
                    return 1;
                }

                $this->info(sprintf('Processing articles from %s to %s', $fromDate->format('Y-m-d'), $toDate->format('Y-m-d')));
            } catch (\Exception $e) {
                $this->error('Invalid date format. Use Y-m-d (e.g., 2025-01-07)');
                return 1;
            }
        }
        
        $this->info('Starting to process pending video articles...');
        
        try {
            // Get articles that:
            // 1. are of type video
            // 2. gave media that doesn't have a video job
            // 3. source is mobile
            $query = Article::whereHas('media', function($query) {
                $query->whereDoesntHave('videoJob')
                    ->where('collection_name', Article::MEDIA_COLLECTION_NAME)
                    ->where(function($q) {
                        $q->where('mime_type', 'LIKE', 'video/%')
                          ->orWhere('mime_type', 'video/quicktime');
                    });
            })
            // ->whereHas('articleRecommendations')
            ->where('type', 'video')
            ->where('source', 'mobile')
            ->with(['media' => function($query) {
                $query->whereDoesntHave('videoJob')
                    ->where('collection_name', Article::MEDIA_COLLECTION_NAME)
                    ->where(function($q) {
                        $q->where('mime_type', 'LIKE', 'video/%')
                          ->orWhere('mime_type', 'video/quicktime');
                    });
            }]);

            // apply date filters if provided
            if ($fromDate && $toDate) {
                $query->whereBetween('created_at', [$fromDate, $toDate]);
            }

            // always order by oldest first
            $articles = $query->oldest('created_at')->get();

            if ($articles->isEmpty()) {
                $this->info('No pending video articles found.');
                return 0;
            }

            $this->info(sprintf('Found %d articles to process', $articles->count()));

            foreach ($articles as $article) {
                try {
                    $this->info('Processing article ID: ' . $article->id . ' (created at: ' . $article->created_at . ')');
                    
                    // get the video media
                    $mediaModel = $article->media->first();
                    
                    if ($mediaModel) {
                        $this->info('--  Media ID: ' . $mediaModel->id);
                        ByteplusVODProcess::dispatch($mediaModel);
                        
                        $this->info(sprintf(
                            'Dispatched VOD process for article ID: %d, media ID: %d',
                            $article->id,
                            $mediaModel->id
                        ));
                        
                        // Add some delay to prevent overwhelming the queue
                        sleep(1);
                    }
                } catch (\Exception $e) {
                    Log::error('Error processing video article', [
                        'article_id' => $article->id,
                        'error' => $e->getMessage()
                    ]);
                    $this->error(sprintf(
                        'Failed to process article ID: %d - %s',
                        $article->id,
                        $e->getMessage()
                    ));
                }
            }

            $this->info('Finished processing pending video articles.');
            Log::info('[ProcessPendingVideoArticlesToVOD] Finished processing pending video articles.', [
                'processed_articles' => $articles->pluck('id')->toArray(),
                'date_range' => $fromDate && $toDate ? [
                    'from' => $fromDate->format('Y-m-d'),
                    'to' => $toDate->format('Y-m-d')
                ] : null
            ]);

            return 0;

        } catch (\Exception $e) {
            Log::error('Error in ProcessPendingVideoArticlesToVOD', [
                'error' => $e->getMessage(),
                'date_range' => $fromDate && $toDate ? [
                    'from' => $fromDate->format('Y-m-d'),
                    'to' => $toDate->format('Y-m-d')
                ] : null
            ]);
            $this->error('Command failed: ' . $e->getMessage());
            return 1;
        }
    }
}