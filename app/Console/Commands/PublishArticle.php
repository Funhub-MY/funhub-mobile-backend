<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;

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
            foreach ($articles as $article) {
                $this->info('Publishing article: '.$article->id);
                $article->update(['status' => Article::STATUS_PUBLISHED]);
                $this->info('Article published: '.$article->id);
            }
            Log::info('[PublishArticle] Articles published', ['article_ids' => $articles->pluck('id')]);
        }

        return Command::SUCCESS;
    }
}
