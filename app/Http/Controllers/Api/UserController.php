<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        // ensure user is not blocked 
        if ($user && $user->usersBlocked->contains(auth()->user()->id)) {
            return response()->json([
                'message' => 'You are blocked by user'
            ], 403);
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
                'user_id' => auth()->id(),
                'blockable_type' => User::class,
                'blockable_id' => $userToBlock->id
            ]);

            return response()->json(['message' => 'User blocked']);
        } else {
            return response()->json(['message' => 'You have already blocked this user'], 422);
        }
    }

}
