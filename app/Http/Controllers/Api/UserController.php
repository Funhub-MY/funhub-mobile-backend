<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicArticleResource;
use App\Http\Resources\PublicUserResource;
use App\Http\Resources\UserBlockResource;
use App\Http\Resources\UserResource;
use App\Jobs\PopulateLocationAddressForUser;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Comment;
use App\Models\Interaction;
use App\Models\Location;
use App\Models\LocationRating;
use App\Models\MerchantOfferClaim;
use App\Models\MerchantOfferClaimRedemptions;
use App\Models\Product;
use App\Models\StoreRating;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserHistoricalLocation;
use App\Models\UserTutorialCompletion;
use App\Services\Mpay;
use App\Services\OtpRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

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
            return response()->json(['message' => __('messages.error.user_controller.User_not_found')], 404);
        }

        // if user status is archived, 404
        if ($user->status == User::STATUS_ARCHIVED) {
            return response()->json(['message' => __('messages.error.user_controller.User_not_found')], 404);
        }

        return new \App\Http\Resources\UserResource($user, false);
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
            return response()->json(['message' => __('messages.error.user_controller.You_have_already_reported_this_comment')], 422);
        }

        return response()->json(['message' => __('messages.success.user_controller.Comment_reported')]);
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

            // unfollow each other
            $userToBlock->unfollow(auth()->user());
            auth()->user()->unfollow($userToBlock);

            return response()->json(['message' => __('messages.success.user_controller.User_blocked')]);
        } else {
            return response()->json(['message' => __('messages.error.user_controller.You_have_already_blocked_this_user')], 422);
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

            return response()->json(['message' => __('messages.success.user_controller.User_unblocked')]);
        } else {
            return response()->json(['message' => __('messages.error.user_controller.You_have_not_blocked_this_user')], 422);
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
        $blockedUsers = UserBlock::disableCache()
            ->where('user_id', auth()->id())
            ->where('blockable_type', User::class)
            ->with('blockable')
            ->get();

        if ($blockedUsers->count() <= 0) {
            return response()->json(['message' => __('messages.error.user_controller.No_blocked_users')], 404);
        }

        $users = User::whereIn('id', $blockedUsers->pluck('blockable_id')->toArray())
            ->paginate(config('app.paginate_per_page'));

        return UserResource::collection($users);
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
     * Delete Account Request for OTP first (Phone No registered users only)
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User
     * @response scenario=success {
     * "message": "OTP sent to your registered mobile number."
     * }
     */
    public function postDeleteAccountRequestOtp(Request $request)
    {
        // check if logged in user is registered with social login, then this step is invalid
        if (auth()->user()->google_id || auth()->user()->facebook_id || auth()->user()->apple_id) {
            return response()->json(['message' => __('messages.error.user_controller.You_cannot_delete_your_account_if_you_are_logged_in_with_Google_or_Facebook')], 403);
        }

        // fire to user registered mobile no
        $user = auth()->user();
        $otpService = new OtpRequestService();

        try {
            $otpService->sendOtp($user->id, $user->phone_country_code, $user->phone_no, 'delete_account');
        } catch (\Exception $e) {
            Log::error('Error sending OTP for delete account', ['error' => $e->getMessage()]);
            return response()->json(['message' => __('messages.error.user_controller.Error_sending_otp')], 422);
        }

        return response()->json(['message' => __('messages.success.user_controller.Account_deletion_sent_otp')]);
    }

    /**
     * Delete My Account
     *
     * @param Request $request
     * @return void
     *
     * @group User
     * @bodyParam reason string required The reason for deleting the account. Example: I am not using this account anymore.
     * @bodyParam otp string The OTP sent to your registered mobile number(only applies to user registered with phone no). Example: 123456
     * @response scenario=success {
     * "message": "Account deleted successfully."
     * }
     */
    public function postDeleteAccount(Request $request)
    {
        $this->validate($request, [
            'reason' => 'required|string'
        ]);

        // if user is logged in with phone_no, not social, then requires otp
        if (auth()->user()->phone_no && !auth()->user()->google_id && !auth()->user()->facebook_id && !auth()->user()->apple_id) {
            $this->validate($request, [
                'otp' => 'required'
            ]);
            $otpService = new OtpRequestService();
            $otpVerified = $otpService->verifyOtp(auth()->user()->id, $request->otp);

            if (!$otpVerified) {
                return response()->json(['message' => __('messages.error.user_controller.Invalid_otp')], 422);
            }
        }

        // archive all articles by this user
        $user = auth()->user();

        // archive all articles by this user
        $user->articles()->update([
            'status' => Article::STATUS_ARCHIVED
        ]);

        // // archive all comments by this user
        // $user->comments()->update([
        //     'status' => Comment::STATUS_HIDDEN
        // ]);

        // // archive all interactions by this user
        // $user->interactions()->update([
        //     'status' => Interaction::STATUS_HIDDEN
        // ]);

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

        // unset user's, password
        $user->name = null;
        $user->username = null;
        $user->password = null;
        $user->status = User::STATUS_ARCHIVED;
		$user->google_id = null;
		$user->facebook_id = null;
		$user->apple_id = null;
        $user->save();

        return response()->json(['message' => __('messages.success.user_controller.Account_deleted_successfully')]);
    }

    /**
     * Get auth user details
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     *
     * @group User
     *
     * @response scenario=success {
     * "user": {
     * },
     * "token": ""
     *
     */
    public function getAuthUserDetails()
    {
        $userId = auth()->user()->id;
        $user = User::find($userId);
        // if user status is archived, 404
        if ($user->status == User::STATUS_ARCHIVED) {
            return response()->json(['message' => __('messages.error.user_controller.User_not_found')], 404);
        }
        // load user followings and followers count with active users only
        $user->load(['followings' => function ($query) {
            $query->where('status', User::STATUS_ACTIVE);
        }, 'followers' => function ($query) {
            $query->where('status', User::STATUS_ACTIVE);
        }, 'tutorialCompletions']);

        $userData = new UserResource($user, true);

        $token = $user->createToken('authToken')->plainTextToken;

        return response()->json([
            'user' => $userData,
            'token' => $token,
        ], 200);
    }

    /**
     * Get a public user
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     *
     * @group User
     * @urlParam user required The id of the user. Example: 1
     * @response scenario=success {
     * "user": {
     * }
     *
     */
    public function getPublicUser(User $user)
    {
        // ensure user is not blocking me or i'm blocking this user
        if (auth()->user()->isBlocking($user) || $user->isBlocking(auth()->user())) {
            return response()->json(['message' => __('messages.error.user_controller.User_not_found')], 404);
        }

        // if user status is archived, 404
        if ($user->status == User::STATUS_ARCHIVED) {
            return response()->json(['message' => __('messages.error.user_controller.User_not_found')], 404);
        }

        return new \App\Http\Resources\UserResource($user, false);
    }

    /**
     * Update User Details (name, username, bio, job_title, dob, gender, location, avatar, cover, article_categories)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group User
     * @bodyParam update_type string required Field to update. Example: job_title
     * @bodyParam name string Name of the user (Required if update_type is 'name'). Example: John Doe
     * @bodyParam username string Username of the user (Required if update_type is 'username'). Example: johndoe
     * @bodyParam bio string Bio of the user (Required if update_type is 'bio'). Example: I am a developer
     * @bodyParam job_title string Job title of the user (Required if update_type is 'job_title'). Example: Developer
     * @bodyParam day integer Day of the date of birth (Required if update_type is 'dob'). Example: 1
     * @bodyParam month integer Month of the date of birth (Required if update_type is 'dob'). Example: 1
     * @bodyParam year integer Year of the date of birth (Required if update_type is 'dob'). Example: 1990
     * @bodyParams gender string Male or Female (Required if update_type is 'gender'). Example: male,female
     * @bodyParam country_id integer Country id of the user (Required if update_type is 'location'). Example: 1
     * @bodyParam state_id integer State id of the user (Required if update_type is 'location'). Example: 1
     * @bodyParam avatar file One image file to upload,Avatar of the user (Required if update_type is 'avatar').
     * @bodyParam cover file One image file to upload,Cover of the user (Required if update_type is 'cover').
     * @bodyParam category_ids array Array of article category ids (Required if update_type is 'article_categories'). Example: [1,2,3]
     * @response status=200 scenario="success" {
     * "user":
     * {
     *  "id": 1,
     *  "name": "John Doe"
     *  "username": "johndoe"
     *  "email": "johndoe@gmail.com"
     *  "verified_email": true
     *  "auth_provider": "email"
     *  "avatar": "https://domain.com/storage/avatars/1/avatar.jpg"
     *  "avatar_thumb": "https://domain.com/storage/avatars/1/avatar_thumb.jpg"
     *  "bio": "I am a developer"
     *  "cover": "https://domain.com/storage/covers/1/cover.jpg"
     *  "articles_published_count": 0
     *  "following_count": 0
     *  "followers_count": 0
     *  "has_completed_profile": false
     *  "has_avatar": true
     *  "point_balance": 0
     *  "unread_notifications_count": 0
     *  "is_following": false
     *  "dob": "1990-01-01",
     *  "gender": "male",
     *  "job_title": "Engineer",
     *  "country_id": 1,
     *  "state_id": 1,
     *  "category_ids": [1,2,3]
     * },
     * "message": "Field updated"
     *
     * }
     * @response status=401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response status=500 scenario="Error updating user details" {"message": "Error updating user details", "error": "Error message"}
     */
    public function postUpdateUserDetails(Request $request)
    {

        try {
                $user = auth()->user();

                $request->validate([
                    'update_type' => 'required|string',
                ]);

                $updateType = $request->input('update_type');

                switch ($updateType) {
                    case 'name':
                        return $this->updateName($request, $user);
                    case 'username':
                        return $this->updateUsername($request, $user);
                    case 'bio':
                        return $this->updateBio($request, $user);
                    case 'job_title':
                        return $this->updateJobTitle($request, $user);
                    case 'dob':
                        return $this->updateDob($request, $user);
                    case 'gender':
                        return $this->updateGender($request, $user);
                    case 'location':
                        return $this->updateLocation($request, $user);
                    case 'avatar':
                        return $this->updateAvatar($request, $user);
                    case 'cover':
                        return $this->updateCover($request, $user);
                    case 'article_categories':
                        return $this->linkArticleCategoriesInterests($request, $user);
                    default:
                        return response()->json([
                            'message' => __('messages.error.user_controller.Invalid_update_type'),
                        ], 422);
                }
            } catch (\Exception $e) {
                return response()->json([
                    'message' => __('messages.error.user_controller.Error_updating_user_details'),
                    'error' => $e->getMessage(),
                ], 500);
            }
    }

    /**
     * Update user password (only for login with OTP)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group User
     * @bodyParam old_password string required The old password of the user. Example: abcd1234
     * @bodyParam new_password string required The new password of the user. Example: abcd1234
     * @bodyParam new_password_confirmation string required The new password confirmation of the user. Example: abcd1234
     * @response status=200 scenario="success" {
     * "message": "Password updated",
     * "user":
     *      * {
     *  "id": 1,
     *  "name": "John Doe"
     *  "username": "johndoe"
     *  "email": "johndoe@gmail.com"
     *  "verified_email": true
     *  "auth_provider": "email"
     *  "avatar": "https://domain.com/storage/avatars/1/avatar.jpg"
     *  "avatar_thumb": "https://domain.com/storage/avatars/1/avatar_thumb.jpg"
     *  "bio": "I am a developer"
     *  "cover": "https://domain.com/storage/covers/1/cover.jpg"
     *  "articles_published_count": 0
     *  "following_count": 0
     *  "followers_count": 0
     *  "has_completed_profile": false
     *  "has_avatar": true
     *  "point_balance": 0
     *  "unread_notifications_count": 0
     *  "is_following": false
     *  "dob": "1990-01-01",
     *  "gender": "male",
     *  "job_title": "Engineer",
     *  "country_id": 1,
     *  "state_id": 1,
     *  "category_ids": [1,2,3]
     * },
     * }
     * @response status=422 scenario="validation error" {
     * "message": "The given data was invalid.",
     * "errors": {
     * "old_password": [
     *  "The old password is incorrect"
     * ],
     * "password": [
     * "The password confirmation does not match."
     * ]
     * }
     */
    public function postUpdatePassword (Request $request)
    {
        // only allow if user is not logged in with google or facebook
        $user = auth()->user();

        if ($user->google_id || $user->facebook_id) {
            return response()->json([
                'message' => __('messages.error.user_controller.You_cannot_change_your_password_if_you_are_logged_in_with_Google_or_Facebook'),
            ], 403);
        }

        $request->validate([
            'old_password' => 'required|string|min:8',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        // check if old password is correct
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'message' => __('messages.error.user_controller.Old_password_is_incorrect'),
            ], 422);
        }

        // update password
        $user->password = Hash::make($request->new_password);
        $user->save();

        $updatedUser = User::find(auth()->user()->id)->first();

        return response()->json([
            'message' => __('messages.success.user_controller.Password_updated'),
            // 'old_password' => $request->old_password,
            // 'new_password' => $request->new_password,
            // 'hash_old_password' => Hash::make($request->old_password),
            // 'hash_new_password' => Hash::make($request->new_password),
            'user' => new UserResource($updatedUser, true),
        ]);
    }

    /**
     * Update User Email
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group User
     * @bodyParam new_email string required Email of the user. Example: john@gmail.com
     * @bodyParam new_email_confirmation string required Email of the user. Example: john@gmail.com
     * @response status=200 scenario="success" {
     * "message": "Email updated",
     * "email": "johndoe@gmail.com",
     * "user": {}
     * }
     * @response status=401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response status=422 scenario="Email already verified for your account" {"message": "Email already verified for your account"}
     */
    public function postUpdateEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email,' . auth()->user()->id,
        ]);

        // if user email still same with current email then reject
        if ($request->has('email') && auth()->user()->email == $request->email) {
            return response()->json(['message' => __('messages.error.user_controller.Email_already_verified_for_your_account')], 422);
        }

        $user = auth()->user();
        $user->email = $request->email;
        $user->save();

        /*
        // send verification email
        $user->sendEmailVerificationNotification();
        */

        $updatedUser = User::find(auth()->user()->id)->first();

        return response()->json([
             'message' => __('messages.success.user_controller.Email_updated_and_verification_email_sent'),
             'email' => $updatedUser->email,
             'user' => new UserResource($updatedUser, true),
        ]);
    }

    protected function updateName(Request $request, $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user->name = $request->name;
        $user->save();

        $updatedUser = User::find(auth()->user()->id);
        $userData = new UserResource($updatedUser, true);

        return response()->json([
            'message' => __('messages.success.user_controller.Name_updated'),
            'user' => $userData,
        ]);
    }

    protected function updateUsername(Request $request, $user)
    {
        $request->validate([
            'username' => 'required|string|max:9|unique:users,username,' . auth()->user()->id,
        ]);

        $user->username = $request->username;
        $user->save();

        $updatedUser = User::find(auth()->user()->id);
        $userData = new UserResource($updatedUser, true);

        return response()->json([
            'message' => __('messages.success.user_controller.Username_updated'),
            'user' => $userData,
        ]);
    }

    protected function updateBio(Request $request, $user)
    {
        $request->validate([
            'bio' => 'required|string|max:2000',
        ]);

        $user->bio = $request->bio;
        $user->save();

        $updatedUser = User::find(auth()->user()->id);
        $userData = new UserResource($updatedUser, true);

        return response()->json([
            'message' => __('messages.success.user_controller.Bio_updated'),
            'user' => $userData,
        ]);
    }

    protected function updateJobTitle(Request $request, $user)
    {
        $request->validate([
            'job_title' => 'required|string|max:200',
        ]);

        $user->job_title = $request->job_title;
        $user->save();

        $updatedUser = User::find(auth()->user()->id);
        $userData = new UserResource($updatedUser, true);

        return response()->json([
            'message' => __('messages.success.user_controller.Job_Title_updated'),
            'user' => $userData,
        ]);
    }

    protected function updateDob(Request $request, $user)
    {
        $request->validate([
            'day' => 'required|integer|min:1|max:31',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:1900|max:'. (date('Y') - 18),
        ]);

        $user->dob = $request->year . '-' . $request->month . '-' . $request->day;
        $user->save();

        $updatedUser = User::find(auth()->user()->id);
        $userData = new UserResource($updatedUser, true);

        return response()->json([
            'message' => __('messages.success.user_controller.Date_of_birth_updated'),
            'user' => $userData,
        ]);
    }

    protected function updateGender(Request $request, $user)
    {
        $request->validate([
            'gender' => 'required|in:male,female'
        ]);

        $user->gender = $request->gender;
        $user->save();

        $updatedUser = User::find(auth()->user()->id);
        $userData = new UserResource($updatedUser, true);

        return response()->json([
            'message' => __('messages.success.user_controller.Gender_updated'),
            'user' => $userData,
        ]);
    }

    public function updateLocation(Request $request, $user)
    {
        $request->validate([
            'country_id' => 'required|integer|exists:countries,id',
            'state_id' => 'required|integer|exists:states,id',
        ]);

        $user->country_id = $request->country_id;
        $user->state_id = $request->state_id;
        $user->save();

        $updatedUser = User::find(auth()->user()->id);
        $userData = new UserResource($updatedUser, true);

        return response()->json([
            'message' => __('messages.success.user_controller.Location_updated'),
            'user' => $userData,
        ]);
    }

    protected function updateAvatar(Request $request, $user)
    {
        // image files support jpeg and common phone uploaded files and maximum of 10MB
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg|max:10000',
        ]);

        // check if user has profile picture
        if ($user->avatar) {
            // delete old profile picture
            $user->avatar = null;
            $user->save();

            // delete from spatie media library as well
            $user->clearMediaCollection('avatar');
        }

        // upload new profile picture
        $uploadedAvatar = $user->addMedia($request->avatar)
            ->toMediaCollection('avatar',
                (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default'))
            );

        // save user avatar id
        $user->avatar = $uploadedAvatar->id;
        $user->save();

        // bust avatar cache
        cache()->forget('user_avatar_' . $user->id);

        $updatedUser = User::find(auth()->user()->id);
        $userData = new UserResource($updatedUser, true);

        return response()->json([
            'message' => __('messages.success.user_controller.Avatar_uploaded'),
            'user' => $userData,
            'avatar_id' => $uploadedAvatar->id,
        ]);
    }

    protected function updateCover(Request $request, $user)
    {
        // image files support jpeg and common phone uploaded files and maximum of 10MB
        $request->validate([
            'cover' => 'required|image|mimes:jpeg,png,jpg|max:10000',
        ]);

        // check if user has cover picture
        if ($user->cover) {
            // delete old cover picture
            $user->cover = null;
            $user->save();

            // delete from spatie media library as well
            $user->clearMediaCollection('cover');
        }

        // upload new profile picture
        $uploadedCover = $user->addMedia($request->cover)
            ->toMediaCollection('cover',
                (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default'))
            );

        // save user avatar id
        $user->cover = $uploadedCover->id;
        $user->save();

        cache()->forget('user_cover_' . $user->id);

        $updatedUser = User::find(auth()->user()->id);
        $userData = new UserResource($updatedUser, true);

        return response()->json([
            'message' => __('messages.success.user_controller.Cover_uploaded'),
            'user' => $userData,
            'cover_id' => $uploadedCover->id,
        ]);
    }

    protected function linkArticleCategoriesInterests(Request $request, $user)
    {
        $request->validate([
            'category_ids' => 'required|array',
        ]);

        // validate all category ids exists
        $categories = ArticleCategory::whereIn('id', $request->category_ids)
            ->get();

        if ($categories->count() != count($request->category_ids)) {
            return response()->json([
                'message' => __('messages.error.user_controller.One_or_more_article_category_ids_not_found'),
            ], 422);
        }

        // if the category has a parent_id add into category_ids as well
        if ($categories->where('parent_id', '!=', null)->count()) {
            $request->category_ids = array_merge($request->category_ids, $categories->where('parent_id', '!=', null)->pluck('parent_id')->toArray());
        }

        // check if article category ids exists only sync
        $user->articleCategoriesInterests()->sync($request->category_ids);

        $updatedUser = User::find(auth()->user()->id);
        $userData = new UserResource($updatedUser, true);

        return response()->json([
            'message' => __('messages.success.user_controller.Article_categories_linked_to_user'),
            'category_ids' => $user->articleCategoriesInterests->pluck('id')->toArray(),
            'user' => $userData,
        ]);
    }

    /**
     * Save user tutorial progress
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User
     * @subgroup Tutorial
     * @bodyParam tutorial_step string required The tutorial step. Example: first_time_visit_mission_tab
     * @response scenario=success {
     * "message": "Tutorial progress saved",
     * "tutorial_step": "first_time_visit_mission_tab",
     * "completed_at": "2024-08-09 15:46:46"
     * }
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["tutorial_step": ["The Tutorial Step field is required."] ]}
     */
    public function postTutorialProgress(Request $request)
    {
        $tutorialSteps = config('app.tutorial_steps');

        $this->validate($request, [
            'tutorial_step' => [
                'required',
                'string',
                Rule::in($tutorialSteps)
            ]
        ]);

        $user = auth()->user();

        // create a new record for user tutorial completion

        // ensure user dont have record for this tutorial step
        $userTutorialCompletion = $user->tutorialCompletions()->updateOrCreate([
            'user_id' => $user->id,
            'tutorial_step' => $request->tutorial_step,
        ], [
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => __('messages.success.user_controller.Tutorial_progress_saved'),
            'tutorial_step' => $request->tutorial_step,
            'completed_at' => $userTutorialCompletion->completed_at,
        ]);
    }

    public function getProfileForPublicView(Request $request)
    {
        $this->validate($request, [
            'username' => 'required|string'
        ]);

        // find user by username
        $user = User::where('username', $request->username)
            ->where('status', User::STATUS_ACTIVE)
            ->first();

        if (!$user) {
            return response()->json(['message' => __('messages.error.user_controller.User_not_found')], 404);
        }

        // check if user profile is private
        $latestSettings = $user->profilePrivacySettings()->orderBy('id', 'desc')->first();

        if ($latestSettings && $latestSettings->profile == 'private') {
            return response()->json(['message' => __('messages.error.user_controller.User_profile_private')], 404);
        }

        // get 6 recent articles
        $recentArticles = $user->articles()
            ->where('status', Article::STATUS_PUBLISHED)
            ->orderBy('created_at', 'desc')
            ->paginate(6);

        // return user profile
        return response()->json([
            'user' => new PublicUserResource($user),
            'articles' => PublicArticleResource::collection($recentArticles),
        ]);
    }

    /**
     * Get User Statistics
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User
     * @queryParam from_date string optional Filter by date from (Y-m-d format). Example: 2025-01-01
     * @queryParam to_date string optional Filter by date to (Y-m-d format). Example: 2025-01-16
     * @queryParam product_ids integer optional Filter by product_id. Example: 1,2,3,4 or 1
     *
     * @response scenario=success {
     *     "data": {
     *         "articles_count": 5,
     *         "store_ratings_count": 3,
     *         "vouchers_purchased": 10,
     *         "vouchers_redeemed": 8,
     *         "products_purchased": 8
     *     }
     * }
     */
    public function getUserStatistics(Request $request)
    {
        $userId = auth()->id();
        $query = ['user_id' => $userId];
        $product_ids = $request->has('product_ids') ? explode(',', $request->get('product_ids')) : [1];

        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        // 2. optimize store ratings count - Use DB aggregation with window functions
        $storeRatingsCount = StoreRating::where($query)
            ->when($fromDate, function($q) use ($fromDate) {
                return $q->whereDate('created_at', '>=', $fromDate);
            })
            ->when($toDate, function($q) use ($toDate) {
                return $q->whereDate('created_at', '<=', $toDate);
            })
            ->select('store_id', 'created_at')
            ->orderBy('created_at')
            ->get()
            ->groupBy(function($item) {
                return $item->created_at->format('Y-m-d');
            })
            ->map(function($dayRatings) {
                return min($dayRatings->unique('store_id')->count(), 5);
            })
            ->sum();

        // 3. optimize voucher purchases - Use indexed columns and avoid unnecessary selects
        $vouchersPurchased = MerchantOfferClaim::from('merchant_offer_user')
            ->where($query)
            ->where('status', MerchantOfferClaim::CLAIM_SUCCESS)
            ->when($fromDate, function($q) use ($fromDate) {
                return $q->whereDate('created_at', '>=', $fromDate);
            })
            ->when($toDate, function($q) use ($toDate) {
                return $q->whereDate('created_at', '<=', $toDate);
            })
            ->sum('quantity');

        // 4. optimize voucher redemptions - Use join instead of whereHas for better performance
        $vouchersRedeemed = MerchantOfferClaimRedemptions::from('merchant_offer_claims_redemptions')
            ->join(
                'merchant_offer_user',
                'merchant_offer_user.id',
                '=',
                'merchant_offer_claims_redemptions.claim_id'
            )
            ->where('merchant_offer_claims_redemptions.user_id', $userId)
            ->where('merchant_offer_user.status', MerchantOfferClaim::CLAIM_SUCCESS)
            ->when($fromDate, function($q) use ($fromDate) {
                return $q->whereDate('merchant_offer_claims_redemptions.created_at', '>=', $fromDate);
            })
            ->when($toDate, function($q) use ($toDate) {
                return $q->whereDate('merchant_offer_claims_redemptions.created_at', '<=', $toDate);
            })
            ->sum('merchant_offer_claims_redemptions.quantity');

        // 5. optimize funcard purchases - Use efficient indexing and grouping
        $funcardPurchased = Transaction::where($query)
            ->where('status', Transaction::STATUS_SUCCESS)
            ->where('transactionable_type', Product::class)
            ->whereIn('transactionable_id', $product_ids)
            ->when($fromDate, function($q) use ($fromDate) {
                return $q->whereDate('created_at', '>=', $fromDate);
            })
            ->when($toDate, function($q) use ($toDate) {
                return $q->whereDate('created_at', '<=', $toDate);
            })
            ->groupBy('transactionable_id')
            ->select('transactionable_id as product_id')
            ->selectRaw('COUNT(*) as total_sales')
            ->get();

        return response()->json([
            'data' => [
                'store_ratings_count' => (int) $storeRatingsCount,
                'vouchers_purchased' => (int) $vouchersPurchased,
                'vouchers_redeemed' => (int) $vouchersRedeemed,
                'funcard_purchased' => $funcardPurchased
            ]
        ]);
    }

    /**
     * Update Last Known Location
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group User
     * @bodyParam lat string required The lat of the user's last known location. Example: 3.123456
     * @bodyParam lng string required The lng of the user's last known location. Example: 101.123456
     * @response status=200 {
     * "message": "Location updated"
     * }
     */
    public function postUpdateLastKnownLocation(Request $request)
    {
        // Validate the request
        $this->validate($request, [
            'lat' => 'required|string',
            'lng' => 'required|string',
        ]);
    
        $userId = auth()->id();
        $user = User::select('id', 'last_lat', 'last_lng')
            ->where('id', $userId)
            ->first();
    
        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }
    
        $currentLat = $user->last_lat;
        $currentLng = $user->last_lng;

        // calculate distance if both current and new coordinates are available
        $distance = 0;
        if ($currentLat !== null && $currentLng !== null) {
            $distance = $this->calculateDistance($currentLat, $currentLng, $request->lat, $request->lng);
        }
    
        // update location and create historical record if distance > 300 meters or no previous location
        if ($distance > 300 || $currentLat === null || $currentLng === null) {
           try {
                // update the user's last known location
                User::where('id', $userId)
                ->update([
                    'last_lat' => $request->lat,
                    'last_lng' => $request->lng,
                ]);
    
                // create a new historical location record
                $loc = UserHistoricalLocation::create([
                    'user_id' => $userId,
                    'lat' => $request->lat,
                    'lng' => $request->lng,
                ]);

                // dispatch the job to populate the location address asynchronously
                dispatch(new PopulateLocationAddressForUser($loc));
           } catch (\Exception $e) {
                Log::error('[UserController] Error updating user last location: ' . $e->getMessage());
                return response()->json([
                    'message' => 'Something went wrong'
                ], 500);
           }
    
            return response()->json([
                'message' => 'Location updated'
            ], 200);
        }
    
        return response()->json([
            'message' => 'Location not updated'
        ], 200);
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // in meters
    
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);
    
        $x = ($lon2 - $lon1) * cos(($lat1 + $lat2) / 2);
        $y = ($lat2 - $lat1);
    
        return sqrt($x * $x + $y * $y) * $earthRadius;
    }
}
