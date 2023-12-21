<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\AutoArchiveKeyword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoArchiveArticles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'article:auto-archive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-archive media partners articles that match auto_archive_keyword';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            //get all the keywords stored in auto_archive_keywords table
            $keywords = AutoArchiveKeyword::pluck('keyword')->toArray();

            // Get articles that have an 'imports' relationship and a status of 'draft' or 'published'
            $articles = Article::whereHas('imports')
                ->whereIn('status', [Article::STATUS_DRAFT, Article::STATUS_PUBLISHED])
                ->get();

            // Filter articles that contain any of the keywords in either title or body
            $matchingArticles = $articles->filter(function ($article) use ($keywords) {
                foreach ($keywords as $keyword) {
                    // Check for keyword in both title and body
                    if (stripos($article->title, $keyword) !== false || stripos($article->body, $keyword) !== false) {
                        return true;
                    }
                }
                return false;
            });

            // auto-archive the articles 
            foreach ($matchingArticles as $matchArticle) {
                $matchArticle->status = Article::STATUS_ARCHIVED;
                $matchArticle->save();
                
                // Log the information for each record
                Log::info('[AutoArchiveArticles]', [
                    'article_id' => $matchArticle->id,
                    'article_status' => $matchArticle->status,
                ]);
            }
        } catch (\Exception $e) {
            // Log any exceptions that occur during execution
            Log::error('[AutoArchiveArticles]', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
