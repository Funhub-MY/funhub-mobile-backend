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
        // ensure user_id is not self
        if ($request->user_id === auth()->user()->id) {
            return response()->json([
                'message' => 'You cannot follow yourself'
            ], 400);
        }

        // check if user already following user or not
        if (auth()->user()->followings()->where('following_id', $request->user_id)->exists()) {
            return response()->json([
                'message' => 'You are already following this user'
            ], 400);
        }

        // logged in user follow anothe user
        auth()->user()->followings()->attach($request->user_id);

        // remove model cache
        auth()->user()->load('followings');

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

        $unfollowedUser = User::find($request->user_id);

        // remove model cache
        auth()->user()->load('followings');

        if ($unfollowedUser) {
            // detach myself from all articles of this user that i just unfollowed as i'm no longer a follower cant be tagged
            $articlesImTaggedIn = $unfollowedUser->articles()->whereHas('taggedUsers', function ($query) use ($request) {
                $query->where('user_id', auth()->user()->id);
            })->get();
            $articlesImTaggedIn->each(function ($article) {
                $article->taggedUsers()->detach(auth()->user()->id);
            });

            // vice versa
            $myArticles = auth()->user()->articles()->whereHas('taggedUsers', function ($query) use ($request) {
                $query->where('user_id', $request->user_id);
            })->get();
            $myArticles->each(function ($article) use ($request) {
                $article->taggedUsers()->detach($request->user_id);
            });
        }

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
     * @queryParam query string optional Search query for name of followers. Example: John
     * @response scenario="success" {
     * "followers": []
     * }
     * @response status=404 scenario="Not Found" {"message": "User not found"}
     */
    public function getFollowers(Request $request)
    {
        $user_id = $request->input('user_id') ?? auth()->id();
        $user = User::findOrFail($user_id);

        if ($user->profile_is_private && $user->id !== auth()->id()) {
            return response()->json([
                'message' => 'User profile is private'
            ], 404);
        }

        $query = $user->followers();
        if ($request->has('query')) {
            $query->where('name', 'like', '%' . $request->input('query') . '%');
        }

        $followers = $query->paginate(config('app.paginate_per_page'));

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
     * @queryParam query string optional Search query for name of followings. Example: John
     * @response scenario="success" {
     * "followings": []
     * }
     * @response status=404 scenario="Not Found" {"message": "User not found"}
     */
    public function getFollowings(Request $request)
    {
        $user_id = $request->input('user_id') ?? auth()->id();
        $user = User::findOrFail($user_id);

        if ($user->profile_is_private && $user->id !== auth()->id()) {
            return response()->json([
                'message' => 'User profile is private'
            ], 404);
        }

        $query = $user->followings();
        if ($request->has('query')) {
            $query->where('name', 'like', '%' . $request->input('query') . '%');
        }
        $followings = $query->paginate(config('app.paginate_per_page'));

        return UserResource::collection($followings);
    }
}
