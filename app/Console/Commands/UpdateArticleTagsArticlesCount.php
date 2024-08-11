<?php

namespace App\Console\Commands;

use App\Models\ArticleTag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateArticleTagsArticlesCount extends Command
{
    protected $signature = 'articles:update-tags-count';

    protected $description = 'Update the article_tags_articles_count table with the latest articles count';

    public function handle()
    {
        // Truncate the article_tags_articles_count table
        DB::table('article_tags_articles_count')->truncate();

        // Get the distinct tag names
        $tagNames = ArticleTag::distinct('name')->pluck('name');

        // Process each tag name
        foreach ($tagNames as $tagName) {
            $articlesCount = DB::table('article_tags')
                ->join('articles_article_tags', 'article_tags.id', '=', 'articles_article_tags.article_tag_id')
                ->where('article_tags.name', $tagName)
                ->count();

            DB::table('article_tags_articles_count')->updateOrInsert(
                ['name' => $tagName],
                ['articles_count' => $articlesCount, 'updated_at' => now()]
            );

            $this->info('Updated article tag: ' . $tagName . ' with ' . $articlesCount . ' articles');
        }

        $this->info('ArticleTagsArticlesCount table updated successfully.');
    }
}
