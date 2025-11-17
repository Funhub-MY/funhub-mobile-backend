<?php

namespace App\Observers;

use Exception;
use Illuminate\Support\Facades\Log;

class ArticleFeedWhitelistUserObserver
{
    // on deleted
    public function deleted($articleFeedWhitelistUser)
    {
        try {
            // remove user articles from search index(algolia)
            $articleFeedWhitelistUser->user->articles->each(function ($article) {
                $article->searchable();
            });
            Log::info('[ArticleFeedWhitelistUserObserver] After deleted, user articles removed from search index(algolia)', [
                'user_id' => $articleFeedWhitelistUser->user_id,
            ]);
        } catch (Exception $e) {
            Log::error('[ArticleFeedWhitelistUserObserver] After deleted, unable to re-index whitelisted user articles', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
