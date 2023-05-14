<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Comment;
use App\Models\MerchantOffer;
use App\Models\View;
use Illuminate\Http\Request;

class ViewController extends Controller
{
    /**
     * Record view for viewable
     * This is used for recording views for articles, comments, and merchant offers
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @group View
     * @bodyParam viewable_type string required The type of the viewable. Example: article/comment/merchant_offer
     * @bodyParam viewable_id int required The id of the viewable. Example: 1
     * @response scenario="success" {
     * "message": "View recorded"
     * }
     */
    public function postView(Request $request)
    {
        $this->validate($request, [
            'viewable_type' => 'required|in:article,comment,merchant_offer',
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
        }

        // each time user view a viewable, new record must be created
        $view = View::create([
            'user_id' => auth()->id(),
            'viewable_type' => $request->viewable_type,
            'viewable_id' => $request->viewable_id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'View recorded',
        ]);
    }

    /**
     * Get views for viewable type
     * This is used for getting views for articles, comments, and merchant offers
     * 
     * @param string $type
     * @param int $id
     * 
     * @return \Illuminate\Http\JsonResponse
     * 
     * @group View
     * @urlParam type string required The type of the viewable. Example: article/comment/merchant_offer
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
}
