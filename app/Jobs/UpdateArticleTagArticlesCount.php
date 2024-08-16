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
        $articlesCount = ArticleTag::where('name', $this->articleTag->name)
            ->withCount('articles')
            ->first();

        if (!$articlesCount) {
            Log::error('ArticleTagArticlesCount not found for article tag: ' . $this->articleTag->name);
            return;
        }

        try {
            DB::table('article_tags_articles_count')->updateOrInsert(
                ['name' => $this->articleTag->name],
                ['articles_count' => $articlesCount->articles_count, 'updated_at' => now()]
            );
        } catch (\Exception $e) {
            Log::error('Error updating article tag articles count: ' . $e->getMessage(), [
                'article_tag' => $this->articleTag->name,
                'error' => $e->getMessage()
            ]);
        }
    }
}
