<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class UserFollowingController extends Controller
{
    /**
     * Follow another user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @group User
     * @subgroup Followings
     * @bodyParam user_id int required The id of the user to follow
     * @response scenario="success" {
     * "message": "You are now following this user"
     * }
     * @response status=400 scenario="Not Found" {"message": "You are already following this user"}
     */
    public function follow(Request $request)
    {
        // check if user already following user or not
        if (auth()->user()->followings()->where('following_id', $request->user_id)->exists()) {
            return response()->json([
                'message' => 'You are already following this user'
            ], 400);
        }

        // logged in user follow anothe user
        auth()->user()->followings()->attach($request->user_id);

        return response()->json([
            'message' => 'You are now following this user'
        ], 200);
    }

    /**
     * Unfollow another user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @group User
     * @subgroup Followings
     * @bodyParam user_id int required The id of the user to unfollow
     * @response scenario="success" {
     * "message": "You are now unfollowing this user"
     * }
     * @response status=400 scenario="Not Found" {"message": "You are not following this user"}
     */
    public function unfollow(Request $request)
    {
        // check if user already following user or not
        if (!auth()->user()->followings()->where('following_id', $request->user_id)->exists()) {
            return response()->json([
                'message' => 'You are not following this user'
            ], 400);
        }

        // logged in user unfollow anothe user
        auth()->user()->followings()->detach($request->user_id);

        return response()->json([
            'message' => 'You are now unfollowing this user'
        ], 200);
    }

    /**
     * Get all followers
     * 
     * @return \Illuminate\Http\JsonResponse
     * 
     * @group User
     * @subgroup Followers
     * @response scenario="success" {
     * "followers": []
     * }
     */
    public function getFollowers()
    {
        $followers = auth()->user()->followers()
            ->paginate(config('app.paginate_per_page'));

        return UserResource::collection($followers);
    }

    /**
     * Get all followings
     * 
     * @return \Illuminate\Http\JsonResponse
     * 
     * @group User
     * @subgroup Followings
     * @response scenario="success" {
     * "followings": []
     * }
     */
    public function getFollowings()
    {
        $followings = auth()->user()->followings()
            ->paginate(config('app.paginate_per_page'));

        return UserResource::collection($followings);
    }
}
