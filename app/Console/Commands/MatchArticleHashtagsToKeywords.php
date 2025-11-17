<?php

namespace App\Console\Commands;

use App\Models\SearchKeyword;
use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MatchArticleHashtagsToKeywords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:match-articles-tags-to-keywords';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Matches article hashtags to search keywords';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // get all articles
        // loop through each article and get the hashtags
        // loop through each hashtag and check if it exists in the search_keywords table
        // if yes, then add the article_id and search_keyword_id to the search_keywords_articles table
        // if no, then create a new search_keyword and add the article_id and search_keyword_id to the search_keywords_articles table

        $this->info('Matching articles hashtags to keywords');

        // get all articles ids in the search_keywords_articles table as we do not want to sync those already donde
        $articleIds = DB::table('search_keywords_articles')->pluck('article_id')->toArray();

        $articles = Article::has('tags')
            ->published()
            ->whereNotIn('id', $articleIds)
            ->get();

        $this->info('Total articles yet to keywords synced from hashtags found: ' . $articles->count());

        foreach ($articles as $article) {
            $this->info('Processing article ID: ' . $article->id);

            $hashtags = $article->tags;

            $this->info('Total hashtags found: ' . $hashtags->count());

            foreach ($hashtags as $hashtag) {
                $searchKeyword = SearchKeyword::where('keyword', $hashtag->name)->first();

                if ($searchKeyword) {
                    $this->info('Search keyword found: ' . $searchKeyword->name);

                    $article->searchKeywords()->syncWithoutDetaching([$searchKeyword->id]);

                    $this->info('Search keyword added to article: ' . $searchKeyword->name);
                } else {
                    $this->info('Search keyword not found: ' . $hashtag->name);
                    // remove any # and trim tag->name
                    $tag_name = str_replace('#', '', $hashtag->name);
                    $tag_name = trim($hashtag->name);

                    $newSearchKeyword = SearchKeyword::create([
                        'keyword' => $tag_name,
                    ]);

                    $article->searchKeywords()->syncWithoutDetaching([$newSearchKeyword->id]);

                    $this->info('New search keyword created: ' . $newSearchKeyword->name);
                }
            }
        }
        return Command::SUCCESS;
    }
}
