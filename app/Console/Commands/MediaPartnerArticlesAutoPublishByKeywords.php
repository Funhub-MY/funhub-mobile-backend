<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\MediaPartnerKeyword;
use App\Models\MediaPartnerKeywords;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MediaPartnerArticlesAutoPublishByKeywords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media-partner:auto-publish-by-keywords';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // only applicable for past 7 days articles
        $articles = Article::where('status', Article::STATUS_DRAFT)
            ->whereHas('imports')
            ->where('created_at', '>=', now()->subDays(7))
            ->get();


        foreach ($articles as $article) {
            $combinedContent = $article->title . ' ' . strip_tags(preg_replace('/\s+/', ' ', $article->content));

            // Check against blacklist keywords
            $blacklistKeywords = MediaPartnerKeywords::where('type', 'blacklist')->pluck('keyword')->toArray();
            foreach ($blacklistKeywords as $keyword) {
                if (stripos($combinedContent, $keyword) !== false) {
                    // Article contains a blacklisted keyword, ignore it
                    $this->info("[MediaPartnerArticlesAutoPublishByKeywords] Article {$article->id} ignored due to blacklisted keyword: {$keyword}");
                    continue 2;
                }
            }

            // Check against whitelist keywords
            $whitelistKeywords = MediaPartnerKeywords::where('type', 'whitelist')->pluck('keyword')->toArray();
            foreach ($whitelistKeywords as $keyword) {
                if (stripos($combinedContent, $keyword) !== false) {
                    // Article contains a whitelisted keyword, publish it
                    $article->status = Article::STATUS_PUBLISHED;
                    // hidden_from_home is set to false
                    $article->hidden_from_home = false;
                    $article->save();
                    $this->info("[MediaPartnerArticlesAutoPublishByKeywords] Article {$article->id} published due to whitelisted keyword: {$keyword}");
                    Log::info("[MediaPartnerArticlesAutoPublishByKeywords] Article {$article->id} published due to whitelisted keyword: {$keyword}");
                    continue 2;
                }
            }

            // Article doesn't match any whitelist keyword, ignore it
            $this->info("Article {$article->id} ignored as it doesn't match any whitelist keyword");
        }

        $this->info('Media partner articles processed successfully.');
    }
}
