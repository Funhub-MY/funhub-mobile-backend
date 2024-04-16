<?php

namespace App\Listeners;

use App\Events\ArticleCreated;
use App\Models\Interaction;
use App\Models\Setting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class RecommendationAutoByPass implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        if ($event instanceof ArticleCreated) {
            // if event article owner already in bypass list/article not hidden from home
            // then don't do anything
            if ($event->article->where(function ($query) {
                $query
                ->has('user.articleFeedWhitelist')
                ->orWhere('hidden_from_home', false);
            })->exists()) {
                return; // article alread in recommendation
            }

            // get settings for
            $settings = Setting::whereIn('key', [
                'recommendation_auto_bypass_view',
                'recommendation_auto_bypass_like',
                'recommendation_auto_bypass_share',
                'recommendation_auto_bypass_bookmark'
            ])->get();

            $user = $event->article->user;

            // get user followers count
            $followersCount = $user->followers()->count();

            // total views on user's articles
            $totalViews = $user->articles()->sum('views');

            // total likes on user's articles
            $totalLikes = $user->articles()->sum(['interactions' => function ($query) {
                $query->where('type', Interaction::TYPE_LIKE);
            }]);

            // total shares on user's articles
            $totalShares = $user->articles()->sum(['interactions' => function ($query) {
                $query->where('type', Interaction::TYPE_SHARE);
            }]);

            // total bookmarks on user's articles
            $totalBookmarks = $user->articles()->sum(['interactions' => function ($query) {
                $query->where('type', Interaction::TYPE_BOOKMARK);
            }]);

            // check conditions
            $viewCondition = ($followersCount > 0) && (($totalViews / $followersCount) * 100) >= $settings['recommendation_auto_bypass_view'];
            $likeCondition = ($followersCount > 0) && (($totalLikes / $followersCount) * 100) >= $settings['recommendation_auto_bypass_like'];
            $shareCondition = ($followersCount > 0) && (($totalShares / $followersCount) * 100) >= $settings['recommendation_auto_bypass_share'];
            $bookmarkCondition = ($followersCount > 0) && (($totalBookmarks / $followersCount) * 100) >= $settings['recommendation_auto_bypass_bookmark'];

            if ($viewCondition && $likeCondition && $shareCondition && $bookmarkCondition) {
                $event->article->update(['hidden_from_home' => false]);
                Log::info('[RecommendationAutoByPass] Article ID: '.$event->article->id.' bypassed from recommendation as condition is met', [
                    'article_id' => $event->article->id,
                    'view_over_followers_rate' => ($totalViews / $followersCount) * 100,
                    'like_over_followers_rate' => ($totalLikes / $followersCount) * 100,
                    'share_over_followers_rate' => ($totalShares / $followersCount) * 100,
                    'bookmark_over_followers_rate' => ($totalBookmarks / $followersCount) * 100
                ]);
            }
        }
    }
}
