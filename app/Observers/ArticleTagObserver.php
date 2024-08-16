<?php

namespace App\Observers;

use App\Models\ArticleTag;
use App\Jobs\UpdateArticleTagArticlesCount;

class ArticleTagObserver
{
    public function created(ArticleTag $articleTag)
    {
        UpdateArticleTagArticlesCount::dispatch($articleTag);
    }

    public function updated(ArticleTag $articleTag)
    {
        UpdateArticleTagArticlesCount::dispatch($articleTag);
    }
}
