<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleRecommendation;
use App\Models\Comment;
use App\Models\Location;
use App\Models\MerchantOffer;
use App\Models\User;
use App\Models\View;
use Illuminate\Http\Request;
use Algolia\AlgoliaSearch\InsightsClient;
use App\Models\Store;
use Illuminate\Support\Facades\Log;

class ViewController extends Controller
{
    /**
     * Record view for viewable
     * This is used for recording views for articles, comments, merchant offers, user profiles and location
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group View
     * @bodyParam viewable_type string required The type of the viewable. Example: article/comment/merchant_offer/user_profile/location/store
     * @bodyParam viewable_id int required The id of the viewable. Example: 1
     * @response scenario="success" {
     * "message": "View recorded"
     * }
     */
    public function postView(Request $request)
    {
        $this->validate($request, [
            'viewable_type' => 'required|in:article,comment,merchant_offer,user_profile,location,store',
            'viewable_id' => 'required|integer',
        ]);

        switch($request->viewable_type) {
            case 'article':
                $request->merge(['viewable_type' => Article::class]);
                break;
            case 'comment':
                $request->merge(['viewable_type' => Comment::class]);
                break;
            case 'merchant_offer':
                $request->merge(['viewable_type' => MerchantOffer::class]);
                break;
            case 'user_profile':
                $request->merge(['viewable_type' => User::class]);
                break;
            case 'location':
                $request->merge(['viewable_type' => Location::class]);
                break;
            case 'store':
                $request->merge(['viewable_type' => Store::class]);
                break;
        }

        // each time user view a viewable, new record must be created
        $view = View::create([
            'user_id' => auth()->id(),
            'viewable_type' => $request->viewable_type,
            'viewable_id' => $request->viewable_id,
            'ip_address' => $request->ip(),
        ]);

        // update article recommendation if this is an article view
        if ($request->viewable_type === Article::class && auth()->check()) {
            ArticleRecommendation::where('user_id', auth()->id())
                ->where('article_id', $request->viewable_id)
                ->update(['last_viewed_at' => now()]);
        }

        // record insights
        if ($view->viewable_type === Article::class) {
            //$this->recordInsights($view);
        }

        return response()->json([
            'message' => __('messages.success.view_controller.View_recorded'),
        ]);
    }

    /**
     * Get views for viewable type
     * This is used for getting views for articles, comments, merchant offers, and user profiles
     *
     * @param string $type
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @group View
     * @urlParam type string required The type of the viewable. Example: article/comment/merchant_offer/user_profile/stpres
     * @urlParam id int required The id of the viewable. Example: 1
     * @response scenario="success" {
     * "views": [],
     * "total": 0
     * }
     */
    public function getViews($type, $id)
    {
        $views = View::where('viewable_type', $type)
            ->where('viewable_id', $id)
            ->get();

        return response()->json([
            'views' => $views,
            'total' => $views->count(),
        ]);
    }

    public function recordInsights($view)
    {
        // check if algolia is enabled
        if (!config('scout.algolia.id') || !config('scout.algolia.secret')) {
            return false;
        }

        // check if scout package is installed
        if (!class_exists(InsightsClient::class)) {
            return false;
        }

        $insights = InsightsClient::create(
            config('scout.algolia.id'),
            config('scout.algolia.secret')
        );

        try {
            $response = $insights->sendEvent([
                    'eventType' => 'click',
                    'eventName' => 'Article Clicked',
                    'index' => config('scout.prefix').'articles_index',
                    'userToken' => (string) auth()->id(),
                    'objectIDs' => [(string) $view->viewable_id],
                ]
            );
        } catch (\Exception $e) {
            Log::error('[recordInsights] Error sending events to Algolia', ['error' => $e->getMessage(), 'view' => $view]);
        }
        return true;
    }
}
