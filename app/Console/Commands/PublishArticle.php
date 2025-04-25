<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Article;
use App\Models\ViewQueue; // Keep for potential direct use elsewhere if needed
use App\Services\ArticleViewSchedulerService; // Added service import
use Illuminate\Support\Facades\Log;

class PublishArticle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'article:publish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish articles that are scheduled to be published';

    protected ArticleViewSchedulerService $scheduler;

    /**
     * Create a new command instance.
     *
     * @param ArticleViewSchedulerService $scheduler
     * @return void
     */
    public function __construct(ArticleViewSchedulerService $scheduler)
    {
        parent::__construct();
        $this->scheduler = $scheduler;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $articles = Article::where('status', Article::STATUS_DRAFT)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereDoesntHave('imports') // exclude articles with media partners
            ->where('source', '!=', 'mobile') // exclude articles with source "mobile"
            ->get();

        if (!$articles->isEmpty()) {
            $publishedCount = 0;
            foreach ($articles as $article) {
                $this->info('Publishing article: '.$article->id);
                try {
                    $article->update(['status' => Article::STATUS_PUBLISHED]);
                    $this->info('Article published: '.$article->id);
                    $publishedCount++;

                    // Use the service to schedule views
                    $this->scheduler->scheduleViews($article);

                } catch (\Exception $e) {
                    Log::error('[PublishArticle] Failed to publish or schedule views for article', [
                        'article_id' => $article->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            if ($publishedCount > 0) {
                Log::info('[PublishArticle] Articles published and views scheduled', ['count' => $publishedCount, 'article_ids' => $articles->pluck('id')]);
            }
        } else {
             Log::info('[PublishArticle] No articles to publish.');
        }

        return Command::SUCCESS;
    }
}
