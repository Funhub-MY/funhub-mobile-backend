<?php

namespace App\Http\Controllers\Api;

use App\Events\FollowedUser;
use App\Events\UnfollowedUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\UserFollowed;
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

        $followedUser = User::find($request->user_id);
        event(new FollowedUser(auth()->user(), $followedUser));

        // ensure not sending to self
        if ($followedUser && $followedUser->id !== auth()->user()->id) {
            $followedUser->notify(new \App\Notifications\Newfollower(auth()->user()));
        }

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

        event(new UnfollowedUser(auth()->user(), User::find($request->user_id)));

        return response()->json([
            'message' => 'You are now unfollowing this user'
        ], 200);
    }

    /**
     * Get all followers of user id or logged in user
     * 
     * @return \Illuminate\Http\JsonResponse
     * 
     * @group User
     * @subgroup Followers
     * @urlParam user_id int optional The id of the user, if not provided will use Logged In User ID. Example: 1
     * @response scenario="success" {
     * "followers": []
     * }
     * @response status=404 scenario="Not Found" {"message": "User not found"}
     */
    public function getFollowers(Request $request)
    {
        $user_id = $request->input('user_id') ?? auth()->id();
        $user = User::findOrFail($user_id);

        $followers = $user->followers()
            ->paginate(config('app.paginate_per_page'));

        return UserResource::collection($followers);
    }

    /**
     * Get all followings of user id or logged in user
     * 
     * @return \Illuminate\Http\JsonResponse
     * 
     * @group User
     * @subgroup Followings
     * @urlParam user_id int optional The id of the user, if not provided will use Logged In User ID. Example: 1
     * @response scenario="success" {
     * "followings": []
     * }
     * @response status=404 scenario="Not Found" {"message": "User not found"}
     */
    public function getFollowings(Request $request)
    {
        $user_id = $request->input('user_id') ?? auth()->id();
        $user = User::findOrFail($user_id);

        $followings = $user->followings()
            ->paginate(config('app.paginate_per_page'));

        return UserResource::collection($followings);
    }
}
