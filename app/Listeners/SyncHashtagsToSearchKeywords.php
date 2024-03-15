<?php

namespace App\Listeners;

use App\Models\Article;
use Illuminate\Contracts\Queue\ShouldQueue;

class SyncHashtagsToSearchKeywords implements ShouldQueue
{
    protected $article;
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(Article $article)
    {
        $this->article = $article;
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        // check if the article has any hashtags
        if ($this->article->tags) {
            $this->article->tags->each(function ($tag) {
                // check if the hashtag exists in the search_keywords table
                $searchKeyword = \App\Models\SearchKeyword::where('keyword', $tag->name)->first();

                //TODO: use service check for explicit keywords

                if ($searchKeyword) {
                    // if yes, then add the article_id and search_keyword_id to the search_keywords_articles table
                    $this->article->searchKeywords()->syncWithoutDetaching([$searchKeyword->id]);
                } else {
                    // if no, then create a new search_keyword and add the article_id and search_keyword_id to the search_keywords_articles table
                    $newSearchKeyword = \App\Models\SearchKeyword::create([
                        'keyword' => $tag->name,
                    ]);

                    $this->article->searchKeywords()->syncWithoutDetaching([$newSearchKeyword->id]);
                }
            });
        }
    }
}
