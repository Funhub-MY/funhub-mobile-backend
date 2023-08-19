<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserBlockResource;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Interaction;
use App\Models\Location;
use App\Models\LocationRating;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Get a user
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     *
     * @group User
     * @urlParam user required The id of the user. Example: 1
     * @response scenario=success {
     * "data": {
     * }
     *
     */
    public function show(User $user)
    {
        // ensure user is not blocking me or i'm blocking this user
        if (auth()->user()->isBlocking($user) || $user->isBlocking(auth()->user())) {
            return response()->json(['message' => 'User not found'], 404);
        }
        return new \App\Http\Resources\UserResource($user);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        //
    }

    /**
     * Report a user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JsonResponse
     *
     * @group User
     * @subgroup Reports
     * @bodyParam user_id integer required The id of the comment. Example: 1
     * @bodyPara3m reason string required The reason for reporting the comment. Example: Spam
     * @bodyParam violation_type required The violation type of this report
     * @bodyParam violation_level required The violation level of this report
     * @bodyParam also_block_user boolean optional Whether to block the user or not. Example: true
     * @response scenario=success {
     * "message": "Comment reported",
     * }
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["user_id": ["The User Id field is required."] ]}
     * @response status=422 scenario="Invalid Form Fields" {"message": "You have already reported this comment" ]}
     */
    public function postReportUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'reason' => 'required|string',
            'violation_level' => 'required|integer',
            'violation_type' => 'required|string'
        ]);
        $user = User::where('id', request('user_id'))->firstOrFail();

        // check if user has reported this comment before if not create
        if (!$user->reports()->where('user_id', auth()->id())->exists()) {
            $user->reports()->create([
                'user_id' => auth()->id(),
                'reason' => request('reason'),
                'violation_level' => request('violation_level'),
                'violation_type' => request('violation_type'),
            ]);

            // block user if also_block_user is true
            if (request('also_block_user')) {
                // create block
                UserBlock::firstOrNew([
                    'user_id' => auth()->id(),
                    'blockable_type' => User::class,
                    'blockable_id' => $user->id
                ],[
                    'user_id' => auth()->id(),
                    'blockable_type' => User::class,
                    'blockable_id' => $user->id
                ]);
            }
        } else {
            return response()->json(['message' => 'You have already reported this comment'], 422);
        }

        return response()->json(['message' => 'Comment reported']);
    }

    /**
     * Block a user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JsonResponse
     *
     * @group User
     * @subgroup Blocks
     * @bodyParam user_id integer required The id of the user. Example: 1
     * @bodyParam reason string optional The reason for blocking the user. Example: Spam
     * @response scenario=success {
     * "message": "User blocked",
     * }
     */
    public function postBlockUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'reason' => 'string'
        ]);

        $userToBlock = User::where('id', request('user_id'))->firstOrFail();

        $userBlockedByMe = UserBlock::where('user_id', auth()->id())
            ->where('blockable_type', User::class)
            ->where('blockable_id', $userToBlock->id)
            ->first();

        if (!$userBlockedByMe) {
            // create block
            UserBlock::create([
                'user_id' => auth()->id(), // self
                'reason' => $request->input('reason'),
                'blockable_type' => User::class, // type of blockable
                'blockable_id' => $userToBlock->id // person i block
            ]);

            return response()->json(['message' => 'User blocked']);
        } else {
            return response()->json(['message' => 'You have already blocked this user'], 422);
        }
    }

    /**
     * Unblock a user
     *
     * @param Request $request
     * @return void
     *
     * @group User
     * @subgroup Blocks
     * @bodyParam user_id integer required The id of the user. Example: 1
     * @response scenario=success {
     * "message": "User unblocked",
     * }
     */
    public function postUnblockUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
        ]);

        $userToUnblock = User::where('id', request('user_id'))->firstOrFail();

        $userBlockedByMe = UserBlock::where('user_id', auth()->id())
            ->where('blockable_type', User::class)
            ->where('blockable_id', $userToUnblock->id)
            ->first();

        if ($userBlockedByMe) {
            // delete block
            $userBlockedByMe->delete();

            return response()->json(['message' => 'User unblocked']);
        } else {
            return response()->json(['message' => 'You have not blocked this user'], 422);
        }
    }

    /**
     * Get My Blocked Users List
     *
     * @return void
     *
     * @group User
     * @subgroup Blocks
     * @response scenario=success {
     *  data: {}
     * }
     *
     */
    public function getMyBlockedUsers()
    {
        $blockedUsers = UserBlock::where('user_id', auth()->id())
            ->where('blockable_type', User::class)
            ->with('blockable')
            ->paginate(config('app.paginate_per_page'));

        return UserBlockResource::collection($blockedUsers);
    }


    /**
     * Get Users By IDs
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JsonResponse
     *
     * @group User
     * @urlParam user_ids required The ids of the users. Example: 1,2,3
     * @response scenario=success {
     * "data": {
     * }
     * }
     */
    public function getUsersByIds(Request $request)
    {
        $request->validate([
            'user_ids' => 'required',
        ]);

        // explode comma
        $user_ids = explode(',', request()->input('user_ids'));

        // remove user if in auth()->user() blocked list
        $user_ids = array_diff($user_ids, auth()->user()->usersBlocked()->pluck('blockable_id')->toArray());

        $users = User::whereIn('id', $user_ids)->get();

        return \App\Http\Resources\UserResource::collection($users);
    }

    /**
     * Delete My Account
     *
     * @param Request $request
     * @return void
     *
     * @group User
     * @response scenario=success {
     * "message": "Account deleted successfully."
     * }
     */
    public function postDeleteAccount(Request $request)
    {
        $this->validate($request, [
            'reason' => 'required|string'
        ]);

        // archive all articles by this user
        $user = auth()->user();

        // archive all articles by this user
        $user->articles()->update([
            'status' => Article::STATUS_ARCHIVED
        ]);

        // archive all comments by this user
        $user->comments()->update([
            'status' => Comment::STATUS_HIDDEN
        ]);

        // archive all interactions by this user
        $user->interactions()->update([
            'status' => Interaction::STATUS_HIDDEN
        ]);

        // remove user's from any UserBlock
        UserBlock::where('blockable_id', $user->id)
            ->where('blockable_type', User::class)
            ->delete();

        // delete user's article ranks
        $user->articleRanks()->delete();

        // delete user's location ratings
        $locationRatings = LocationRating::where('user_id', $user->id)->get();
        $locationIdsNeedRecalculateRatings = $locationRatings->pluck('location_id')->toArray();
        // recalculate a location avg ratings
        Location::whereIn('id', $locationIdsNeedRecalculateRatings)->get()->each(function ($location) {
            $location->average_ratings = $location->ratings()->avg('rating');
            $location->save();
        });

        // remove user_id from scout index
        $user->unsearchable();

        // add a new record for account deletion for backup purposes
        $user->userAccountDeletion()->create([
            'reason' => $request->input('reason'),
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone_no' => $user->phone_no,
            'phone_country_code' => $user->phone_country_code,
        ]);

        Log::info('User Account Deleted', ['user_id' => $user->id]);

        // unauthorize user
        auth()->user()->tokens()->delete();

        // unset user's phone_no, phone_country_code, email, password
        $user->name = null;
        $user->username = null;
        $user->phone_no = null;
        $user->phone_country_code = null;
        $user->email = null;
        $user->password = null;
        $user->save();

        // soft deletes user
        $user->delete();

        return response()->json(['message' => 'Account deleted successfully.']);
    }
}
