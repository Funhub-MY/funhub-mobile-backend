<?php

namespace App\Jobs;

use App\Models\ArticleTag;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateArticleTagArticlesCount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $articleTag;

    public function __construct(ArticleTag $articleTag)
    {
        $this->articleTag = $articleTag;
    }

    public function handle()
    {
        $articlesCounts = ArticleTag::where('name', $this->articleTag->name)
            ->withCount('articles')
            ->get();

        if (empty($articlesCounts)) {
            Log::error('ArticleTagArticlesCount not found for article tag: ' . $this->articleTag->name);
            return;
        }

        //TODO: get all article tag -> sum all article tag count -> use new article tag count
        $sumArticlesCounts = $articlesCounts->sum('articles_count');

        Log::info('Current ArticleTag Model: ', $articlesCounts->toArray());

        try {
            DB::table('article_tags_articles_count')->updateOrInsert(
                ['name' => $this->articleTag->name],
                ['articles_count' => $sumArticlesCounts, 'updated_at' => now()]
            );
        } catch (\Exception $e) {
            Log::error('Error updating article tag articles count: ' . $e->getMessage(), [
                'article_tag' => $this->articleTag->name,
                'error' => $e->getMessage()
            ]);
        }
    }
}
