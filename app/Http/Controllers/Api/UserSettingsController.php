<?php

namespace App\Http\Controllers\Api;

use App\Services\Sms;
use App\Events\UserSettingsUpdated;
use App\Events\UserReferred;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserSettingsRequest;
use App\Models\ArticleCategory;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Mpay;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class UserSettingsController extends Controller
{
    protected $smsService;
    public function __construct()
    {
        $this->smsService = new Sms(
            [
                'url' => config('services.byteplus.sms_url'),
                'username' => config('services.byteplus.sms_account'),
                'password' => config('services.byteplus.sms_password'),
            ],
            [
                'api_url' => config('services.movider.api_url'),
                'key' => config('services.movider.key'),
                'secret' => config('services.movider.secret'),
            ]
        );
    }

    /**
     * Get settings of logged in user
     *
     * @return JsonResponse
     *
     * @group User Settings
     * @response status=200 scenario="success" {
     *  "name": "John Doe",
     *  "email": "johndoe@gmail.com"
     *  "username": "johndoe",
     *  "dob": "1990-01-01",
     *  "gender": "male",
     *  "bio": "Hello",
     *  "job_title": "Engineer",
     *  "country_id": 1,
     *  "state_id": 1,
     *  "avatar": "https://www.example.com/avatar.jpg",
     *  "avatar_thumb": "https://www.example.com/avatar_thumb.jpg",
     *  "category_ids": [1,2,3]
     * }
     * @response status=401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response status=404 scenario="No settings found yet" {"message": "No settings found yet."}
     */
    public function getSettings()
    {
        // get user name, username, dob, gender, bio, job title, country_id, state_id
        $settings = [
            'name' => auth()->user()->name,
            'email' => auth()->user()->email,
            'username' => auth()->user()->username,
            'dob' => auth()->user()->dob,
            'gender' => auth()->user()->gender,
            'bio' => auth()->user()->bio,
            'job_title' => auth()->user()->job_title,
            'country_id' => auth()->user()->country_id,
            'state_id' => auth()->user()->state_id,
            'avatar' => auth()->user()->avatar_url,
            'avatar_thumb' => auth()->user()->avatar_thumb_url,
            'category_ids' => auth()->user()->articleCategoriesInterests->pluck('id')->toArray(),
        ];

        if ($settings) {
            return response()->json($settings);
        } else {
            return response()->json(['message' => __('messages.error.user_settings_controller.No_settings_found_yet')], 404);
        }
    }


    /**
     * Update User Email
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User Settings
     * @bodyParam email string required Email of the user. Example: john@gmail.com
     * @response status=200 scenario="success" {
     * "message": "Email updated",
     * "email": "johndoe@gmail.com"
     * }
     * @response status=401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response status=422 scenario="Email already verified for your account" {"message": "Email already verified for your account"}
     */
    public function postSaveEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = auth()->user();
        $newEmail = $request->email;

        // if user email still same with current email then reject
        if ($user->email == $newEmail && $user->email_verified_at) {
            return response()->json(['message' => __('messages.error.user_settings_controller.Email_already_verified_for_your_account')], 422);
        }

        // check if the user is using social login
        $isSocialLogin = $user->google_id || $user->facebook_id || $user->apple_id;

        // if not social login, check email uniqueness against non-social login users
        if (!$isSocialLogin) {
            $existingUser = User::where('email', $newEmail)
                ->where('id', '!=', $user->id)
                ->whereNull('google_id')
                ->whereNull('facebook_id')
                ->whereNull('apple_id')
                ->first();

            if ($existingUser) {
                return response()->json(['message' => 'Email already exists, use a different email address'], 422);
            }
        }

        $user = auth()->user();
        $user->email = $request->email;
        // empty the email verification status
        $user->email_verified_at = null; // resets
        $user->save();

        // send verification email
        $user->sendEmailVerificationNotification();

        return response()->json([
             'message' => __('messages.success.user_settings_controller.Email_updated_and_verification_email_sent'),
             'email' => $user->email
        ]);
    }

    /**
     * Verify User Email with Token
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User Settings
     * @bodyParam token string required Token of the user. Example: 123456
     * @response status=200 scenario="success" {
     * "message": "Email Verified"
     * }
     */
    public function verifyEmail(Request $request)
    {
        $this->validate($request, [
            'token' => 'required|min:6|max:6'
        ]);
        $user = auth()->user();
        // check token provided
        if ($user->email_verification_token != $request->token) {
            return response()->json(['message' => __('messages.error.user_settings_controller.Invalid_Token')], 422);
        }
        $user->markEmailAsVerified();

        // fire event
        event(new UserSettingsUpdated($user));

        return response()->json(['message' => __('messages.success.user_settings_controller.Email_Verified')], 200);
    }

    /**
     * Update User Name
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User Settings
     * @bodyParam name string required Name of the user. Example: John Doe
     * @response status=200 scenario="success" {
     * "message": "Name updated",
     * "name": "John Doe"
     * }
     * @response status=401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     */
    public function postSaveName(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = auth()->user();
        $user->name = $request->name;
        $user->save();

        // fire event
        event(new UserSettingsUpdated($user));

        return response()->json([
            'message' => __('messages.success.user_settings_controller.Name_updated'),
            'name' => $user->name,
        ]);
    }

    /**
     * Update User Username
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User Settings
     * @bodyParam username string required Username of the user. Example: johndoe
     * @response status=200 scenario="success" {
     * "message": "Username updated",
     * "username": "johndoe"
     * }
     * @response status=401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     */
    public function postSaveUsername(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:9',
        ]);

        $user = auth()->user();

        // disable username changes if user changed before
        if ($user->usernameChanges()->count() > 0) {
            return response()->json(['message' => __('messages.error.user_settings_controller.Username_already_changed_before')], 422);
        }

        // Check if username already exists
        $usernameExists = User::where('username', $request->username)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($usernameExists) {
            return response()->json([
                'message' => 'The username has already been taken.',
                'is_username_exist' => true
            ], 422);
        }

        // save usernameChange record
        $user->usernameChanges()->create([
            'old_username' => $user->username,
            'new_username' => $request->username,
        ]);

        $user->username = $request->username;
        $user->save();

        // fire event
        event(new UserSettingsUpdated($user));

        return response()->json([
            'message' => __('messages.success.user_settings_controller.Username_updated'),
            'username' => $user->username,
        ]);
    }

    /**
     * Update User Bio
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User Settings
     * @bodyParam bio string required Bio of the user. Example: I am a software engineer
     * @response status=200 scenario="success" {
     * "message": "Bio updated",
     * "bio": "I am a software engineer"
     * }
     * @response status=401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     */
    public function postSaveBio(Request $request)
    {
        $request->validate([
            'bio' => 'required|string|max:2000',
        ]);

        $user = auth()->user();
        $user->bio = $request->bio;
        $user->save();

        // fire event
        event(new UserSettingsUpdated($user));

        return response()->json([
            'message' => __('messages.success.user_settings_controller.Bio_updated'),
            'bio' => $user->bio,
        ]);
    }

    /**
     * Update User Job title
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User Settings
     * @bodyParam job_title string required Job title of the user. Example: Software Engineer
     * @response status=200 scenario="success" {
     * "message": "Job Title updated",
     * "job_title": "Software Engineer"
     * }
     * @response status=401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     */
    public function postSaveJobTitle(Request $request)
    {
        $request->validate([
            'job_title' => 'required|string|max:200',
        ]);

        $user = auth()->user();
        $user->job_title = $request->job_title;
        $user->save();

        // fire event
        event(new UserSettingsUpdated($user));

        return response()->json([
            'message' => __('messages.success.user_settings_controller.Job_Title_updated'),
            'job_title' => $user->job_title,
        ]);
    }

    /**
     * Update User Date of Birth
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User Settings
     * @bodyParam day integer required Day of the date of birth. Example: 1
     * @bodyParam month integer required Month of the date of birth. Example: 1
     * @bodyParam year integer required Year of the date of birth. Example: 1990
     * @response status=200 scenario="success" {
     * "message": "Date of birth updated",
     * "dob": "1990-01-01"
     * }
     *
     * @response status=401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     */
    public function postSaveDob(Request $request)
    {
        $request->validate([
            'day' => 'required|integer|min:1|max:31',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:1900|max:'. (date('Y') - 18),
        ]);

        $user = auth()->user();
        $user->dob = $request->year . '-' . $request->month . '-' . $request->day;
        $user->save();

        // fire event
        event(new UserSettingsUpdated($user));

        return response()->json([
            'message' => __('messages.success.user_settings_controller.Date_of_birth_updated'),
            'dob' => $user->dob,
        ]);
    }

    /**
     * Update User Save Gender
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User Settings
     * @bodyParams gender string required Male or Female. Example: male,female
     *
     * @response status=200 scenario="success" {
     * "message": "Gender updated",
     * "gender": "male"
     * }
     */
    public function postSaveGender(Request $request)
    {
        $request->validate([
            'gender' => 'required|in:male,female'
        ]);

        $user = auth()->user();
        $user->gender = $request->gender;
        $user->save();

        // fire event
        event(new UserSettingsUpdated($user));

        return response()->json([
            'message' => __('messages.success.user_settings_controller.Gender_updated'),
            'gender' => $user->gender
        ]);
    }

    /**
     * Update User Location
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User Settings
     * @bodyParam country_id integer required Country id of the user. Example: 1
     * @bodyParam state_id integer required State id of the user. Example: 1
     *
     * @response status=200 scenario="success" {
     * "message": "Location updated"
     * "country_id": 1,
     * "state_id": 1
     * }
     *
     * @response status=401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     */
    public function postSaveLocation(Request $request)
    {
        $request->validate([
            'country_id' => 'required|integer|exists:countries,id',
            'state_id' => 'required|integer|exists:states,id',
        ]);

        $user = auth()->user();
        $user->country_id = $request->country_id;
        $user->state_id = $request->state_id;
        $user->save();

        // fire event
        event(new UserSettingsUpdated($user));

        return response()->json([
            'message' => __('messages.success.user_settings_controller.Location_updated'),
            'country_id' => $user->country_id,
            'state_id' => $user->state_id,
        ]);
    }

    /**
     * Link Article Categories to User (used for interest tagging)
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User Settings
     * @bodyParam category_ids array required Array of article category ids. Example: [1,2,3]
     * @response status=200 scenario="success" {
     * "message": "Article categories linked to user",
     * "category_ids": [1,2,3]
     * }
     * @response status=401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     */
    public function postLinkArticleCategoriesInterests(Request $request)
    {
        $request->validate([
            'category_ids' => 'required|array',
        ]);

        // validate all category ids exists
        $categories = ArticleCategory::whereIn('id', $request->category_ids)
            ->get();

        if ($categories->count() != count($request->category_ids)) {
            return response()->json([
                'message' => __('messages.error.user_settings_controller.One_or_more_article_category_ids_not_found'),
            ], 422);
        }

        // if the category has a parent_id add into category_ids as well
        $categoryIds = $request->category_ids;
        if ($categories->where('parent_id', '!=', null)->count()) {
            $categoryIds = array_merge($categoryIds, $categories->where('parent_id', '!=', null)->pluck('parent_id')->toArray());
        }

        $user = auth()->user();

        // check if article category ids exists only sync
        $user->articleCategoriesInterests()->sync($categoryIds);

        // fire event
        event(new UserSettingsUpdated($user));

        return response()->json([
            'message' => __('messages.success.user_settings_controller.Article_categories_linked_to_user'),
            'category_ids' => $user->articleCategoriesInterests->pluck('id')->toArray(),
        ]);
    }

    /**
     * Upload or Update User Profile Picture
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User Settings
     * @bodyParam avatar file required One image file to upload.
     * @response status=200 scenario="success" {
     * "message": "Avatar uploaded",
     * "avatar": "url",
     * "avatar_thumb": "url"
     * }
     * @response status=401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     */
    public function postUploadAvatar(Request $request)
    {
        // image files support jpeg and common phone uploaded files and maximum of 10MB
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg|max:10000',
        ]);

        $user = auth()->user();

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

        event(new UserSettingsUpdated($user));

        return response()->json([
            'message' => __('messages.success.user_settings_controller.Avatar_uploaded'),
            'avatar_id' => $uploadedAvatar->id,
            'avatar' => $uploadedAvatar->getUrl(),
            'avatar_thumb' => $uploadedAvatar->getUrl('thumb'),
        ]);
    }

    /**
     * Upload or Update User Cover
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User Settings
     * @bodyParam cover file required One image file to upload.
     * @response status=200 scenario="success" {
     * "message": "Cover uploaded",
     * "cover": "url",
     * "cover_thumb": "url"
     * }
     * @response status=401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     */
    public function postUploadCover(Request $request)
    {
        // image files support jpeg and common phone uploaded files and maximum of 10MB
        $request->validate([
            'cover' => 'required|image|mimes:jpeg,png,jpg|max:10000',
        ]);

        $user = auth()->user();

        // check if user has profile picture
        if ($user->avatar) {
            // delete old profile picture
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

        return response()->json([
            'message' => __('messages.success.user_settings_controller.Cover_uploaded'),
            'cover_id' => $uploadedCover->id,
            'cover' => $uploadedCover->getUrl(),
        ]);
    }

    /**
     * Update user fcm token
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group Notifications
     * @bodyParam fcm_token string required The fcm token of the user. Example: 1
     * @response scenario=success {
     * "success": true
     * }
     */
    public function postSaveFcmToken(Request $request){
        $request->user()->update(['fcm_token'=>$request->fcm_token]);
        return response()->json([
            'success'=> true
        ]);
    }

    /**
     * Update user password (only for login with OTP)
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User Settings
     * @bodyParam old_password string required The old password of the user. Example: abcd1234
     * @bodyParam password string required The new password of the user. Example: abcd1234
     * @bodyParam password_confirmation string required The new password confirmation of the user. Example: abcd1234
     * @response status=200 scenario="success" {
     * "message": "Password updated"
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
                'message' => __('messages.error.user_settings_controller.You_cannot_change_your_password_if_you_are_logged_in_with_Google_or_Facebook'),
            ], 403);
        }

        $request->validate([
            'old_password' => 'required|string|min:8',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // check if old password is correct
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'message' => __('messages.error.user_settings_controller.Old_password_is_incorrect'),
            ], 422);
        }

        // update password
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'message' => __('messages.success.user_settings_controller.Password_updated'),
        ]);
    }

    /**
     * Profile Privacy Setting
     *
     * @param Request $request
     * @return void
     *
     * @group User Settings
     * @bodyParam profile_privacy string required The profile privacy of the user. Example: public
     *
     * @response status=200 scenario="success" {
     * "message": "Profile privacy updated",
     * "profile_privacy": "public"
     * }
     */
    public function postUpdateProfilePrivacy(Request $request)
    {
        $request->validate([
            'profile_privacy' => 'required|in:public,private',
        ]);

        $user = auth()->user();

        $latestSettings = $user->profilePrivacySettings()->orderBy('id', 'desc')->first();

        if ($latestSettings && $latestSettings->profile == $request->profile_privacy) {
            return response()->json([
                'message' => __('messages.success.user_settings_controller.Profile_privacy_already_set_to', ['setting' => $request->profile_privacy]),
                'profile_privacy' => $latestSettings->profile,
            ]);
        } else {
            $user->profilePrivacySettings()->create([
                'profile' => $request->profile_privacy,
                'articles' => ($latestSettings) ? $latestSettings->articles : 'public',
            ]);
        }

        Log::info('Profile privacy updated', ['user_id' => $user->id, 'profile_privacy' => $request->profile_privacy, 'latest' => $user->profilePrivacySettings()->orderBy('id', 'desc')->first()]);

        return response()->json([
            'message' => __('messages.success.user_settings_controller.Profile_privacy_updated'),
            'profile_privacy' => $user->profilePrivacySettings()->orderBy('id', 'desc')->first()->profile,
        ]);
    }

    /**
     * Update Phone No (send otp)
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User Settings
     * @bodyParam country_code string required Country code of the user. Example: 60
     * @bodyParam phone_no string required Phone number of the user. Example: 123456789
     * @response scenario=success {
     * "status": "success",
     * "message": "OTP sent"
     * }
     */
    public function postUpdatePhoneNo(Request $request)
    {
        $request->validate([
            'country_code' => 'required|string',
            'phone_no' => 'required|string',
        ]);

        // check phone no has prefix 0 remove it first
        if (substr($request->phone_no, 0, 1) == '0') {
            $request->merge(['phone_no' => substr($request->phone_no, 1)]);
        } else if (substr($request->phone_no, 0, 2) == '60') {
            $request->merge(['phone_no' => substr($request->phone_no, 2)]);
        }

        // check phone no has prefix + remove it first
        if (substr($request->phone_no, 0, 1) == '+') {
            $request->merge(['phone_no' => substr($request->phone_no, 1)]);
        }

        $user = User::where('phone_no', $request->phone_no)
            ->where('phone_country_code', $request->country_code)
            ->first();

        if (!$user) { // does not exists
            $otp = rand(100000, 999999);
            auth()->user()->update([
                'otp' => $otp,
                'otp_expiry' => now()->addMinutes(1),
                'otp_verified_at' => null,
            ]);

            // full no
            $fullPhoneNo = $request->country_code . $request->phone_no;

            // send otp
            $this->smsService->sendSms($fullPhoneNo, config('app.name')." - Your OTP is ".auth()->user()->otp);

            return response()->json([
                'status' => 'success',
                'message' => __('messages.success.auth_controller.OTP_sent')
            ], 200);
        }

        return response()->json(['message' => __('messages.error.auth_controller.Phone_Number_already_registered')], 422);
    }

    /**
     * Update Phone No (Verify OTP)
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User Settings
     * @bodyParam country_code string required Country code of the user. Example: 60
     * @bodyParam phone_no string required Phone number of the user. Example: 123456789
     * @bodyParam otp string required OTP of the user. Example: 123456
     *
     * @response scenario=success {
     * "success": true,
     * "message": "Phone No updated"
     * }
     */
    public function postUpdatePhoneNoVerifyOtp(Request $request)
    {
        $request->validate([
            'country_code' => 'required|string',
            'phone_no' => 'required|string',
            'otp' => 'required|string',
        ]);

        // check phone no has prefix 0 remove it first
        if (substr($request->phone_no, 0, 1) == '0') {
            $request->merge(['phone_no' => substr($request->phone_no, 1)]);
        } else if (substr($request->phone_no, 0, 2) == '60') {
            $request->merge(['phone_no' => substr($request->phone_no, 2)]);
        }

        // check phone no has prefix + remove it first
        if (substr($request->phone_no, 0, 1) == '+') {
            $request->merge(['phone_no' => substr($request->phone_no, 1)]);
        }

        $user = auth()->user();

        if ($user->otp != $request->otp) {
            return response()->json(['message' => __('messages.error.auth_controller.OTP_is_incorrect')], 422);
        }

        if ($user->otp_expiry < now()) {
            return response()->json(['message' => __('messages.error.auth_controller.OTP_has_expired')], 422);
        }

        $user->otp_verified_at = now();
        $user->otp = null; // empty it
        $user->save();

        // ensure user phone no is still not registered yet (one more time)
        $existingUser = User::where('phone_no', $request->phone_no)
            ->where('phone_country_code', $request->country_code)
            ->first();
        if ($existingUser) return response()->json(['message' => __('messages.error.auth_controller.Phone_Number_already_registered')], 422);

        $user->phone_country_code = $request->country_code;
        $user->phone_no = $request->phone_no;
        $user->save();

        $rsvp_user = DB::table('rsvp_users')->where('phone_no',$request->phone_no)->first();

        if ($user && $rsvp_user){
            $user->rsvp = 1;
            $user->save();
        }

        return response()->json([
            'success' => true,
            'message' => __('messages.success.user_settings_controller.Phone_updated')
        ]);
    }

    /**
     * Referral - Get My Referral Code
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User Settings
     * @subgroup Referral
     * @response status=200 scenario="success" {
     * "referral_code": "ABC123",
     * "message": "Come and join with with referral code ABC123"
     * }
     */
    public function getMyReferralCode(Request $request)
    {
        $user = auth()->user();

        // check if user has referral_code generated
        if (!$user->referral_code) {
            $user->referral_code = strtoupper(substr(md5($user->id . $user->username . now()), 0, 7));
            // check if referral code is unique before saving, if not regenerate max 5 times
            $i = 0;
            while (User::where('referral_code', $user->referral_code)->exists() && $i < 5) {
                $user->referral_code = strtoupper(substr(md5($user->id . $user->username . now()), 0, 7));
                $i++;
            }

            $user->save();
            $user->refresh(); // refresh user object
        }
        return response()->json([
            'referral_code' => $user->referral_code,
            'message' => __('messages.success.user_settings_controller.Referral_code_generated',['code' => $user->referral_code])
        ]);
    }

    /**
     * Referral - Save Referral
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User Settings
     * @subgroup Referral
     * @bodyParam referral_code string required Referral code of the user. Example: ABC123
     * @response status=200 scenario="success" {
     * "message": "Referral saved"
     * }
     *
     * @response status=422 scenario="User already referred by someone" {
     * "message": "User already referred by someone"
     * }
     *
     * @response status=422 scenario="Referral code not found" {
     * "message": "Referral code not found"
     * }
     *
     * @response status=422 scenario="You cannot refer yourself" {
     * "message": "You cannot refer yourself"
     * }
     */
    public function postSaveReferral(Request $request)
    {
        // check if user belongs to a referral already or not
        $user = auth()->user();
        if ($user->referred_by_id) {
            return response()->json(['message' => __('messages.error.user_settings_controller.User_already_referred_by_someone')], 422);
        }

        $request->validate([
            'referral_code' => 'required|string',
        ]);

        // check if referral code exists
        $referredBy = User::where('referral_code', $request->referral_code)->first();
        if (!$referredBy || $referredBy->account_restricted) {
            return response()->json(['message' => __('messages.error.user_settings_controller.Referral_code_not_found')], 422);
        }

        // check if user is not referring himself
        if ($referredBy->id == $user->id) {
            return response()->json(['message' => __('messages.error.user_settings_controller.You_cannot_refer_yourself')], 422);
        }

        // check if user is more than 48hours old cannot use referral system
        if (Carbon::parse($user->created_at)->diffInMinutes(now()) >= (config('app.referral_max_hours') * 60)) {
            return response()->json(['message' => __('messages.error.user_settings_controller.Referral_code_expired')], 422);
        }

        // save referred by
        $user->referred_by_id = $referredBy->id;
        $user->referred_at = now();
        $user->save();

        // fire event
        event(new UserReferred($user, $referredBy));

        return response()->json(['message' => __('messages.success.user_settings_controller.Referral_saved')]);
    }


    /**
     * Save OneSignal Subscription Id
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User Settings
     * @bodyParam onesignal_subscription_id string required The OneSignal subscription id. Example: 123456
     * @response scenario=success {
     * "message": "Saved"
     * }
     */
    public function postSaveOneSignalSubscriptionId(Request $request)
    {
        $this->validate($request, [
            'onesignal_subscription_id' => 'required|string'
        ]);

        $user = auth()->user();
        $user->onesignal_subscription_id = $request->onesignal_subscription_id;
        $user->save();

        return response()->json(['message' => 'Saved']);
    }

    /**
     * Save OneSignal User ID
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User Settings
     * @bodyParam onesignal_user_id string required The OneSignal user id. Example: 123456
     * @response scenario=success {
     * "message": "Saved"
     * }
     */
    public function postSaveOneSignalUserId(Request $request)
    {
        $this->validate($request, [
            'onesignal_user_id' => 'required|string'
        ]);

        $user = auth()->user();
        $user->onesignal_user_id = $request->onesignal_user_id;
        $user->save();

        return response()->json(['message' => 'Saved']);
    }

    /**
     * Save Language of User
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User Settings
     * @bodyParam lang string required The language of the user. Example: en/zh
     * @response scenario=success {
     * "message": "Saved"
     * }
     */
    public function postSaveLanguage(Request $request)
    {
        $this->validate($request, [
            'last_lang' => 'required|string|in:en,zh'
        ]);

        $user = auth()->user();
        $user->last_lang = $request->last_lang;
        $user->save();

        return response()->json(['message' => 'Saved']);
    }


    /**
     * Add a Card (Tokenization)
     *
     * @group User Settings
     * @response status=200 {
     *  "status": "success",
     *  "data": {
     *
     *
     * @param Request $request
     * @return void
     */
    public function cardTokenization(Request $request)
    {
        $gateway = new Mpay(
            config('services.mpay.mid'),
            config('services.mpay.hash_key'),
        );

        $user = auth()->user();
        $uuid = $user->id;
        $transaction_no = 'CARD'.strtoupper(Str::random(6)).rand(0, 999); // 20 char
        $redirectUrl = route('payment.card-tokenization.return');

        $transaction = new Transaction();
        $transaction->transaction_no = $transaction_no;
        $transaction->transactionable_type = User::class;
        $transaction->transactionable_id = $user->id;
        $transaction->user_id = $user->id;
        $transaction->amount = 0;
        $transaction->gateway = 'Mpay';
        $transaction->gateway_transaction_id = '';
        $transaction->status = Transaction::STATUS_PENDING;
        $transaction->save();

        $data = $gateway->createCardTokenization(
            $uuid, // user id
            $redirectUrl, // /payment/card-tokenization/return
            $transaction_no, // invno
            $user->full_phone_no,
            $user->email
        );

        Log::info('Mpay Card Tokenization Data: ', [
            'uuid' => $uuid,
            'transaction_no' => $transaction_no,
            'transaction_id' => $transaction->id,
        ]);

        return [
            'status' => 'success',
            'data' => $data,
        ];
    }

    /**
     * Get User Cards
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User Settings
     * @subgroup Card
     * @response status=200 {
     * "cards": []
     * }
     */
    public function getCards(Request $request)
    {
        $user = auth()->user();
        $cards = $user->cards()->get();

        if (!$cards) {
            return response()->json([
                'message' => __('messages.error.user_settings_controller.No_Cards_found')
            ], 404);
        }

        return response()->json([
            'cards' => $cards
        ]);
    }

    /**
     * Remove a Card
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User Settings
     * @subgroup Card
     * @bodyParam card_id integer required The id of the card. Example: 1
     * @response status=200 {
     * "message": "Card removed"
     * }
     */
    public function postRemoveCard(Request $request)
    {
        $request->validate([
            'card_id' => 'required|exists:user_cards,id'
        ]);

        $user = auth()->user();

        $card = $user->cards()->find($request->card_id);

        if (!$card) {
            return response()->json([
                'message' => __('messages.error.user_settings_controller.No_Cards_found')
            ], 404);
        }

        // check if card is default
        if ($card->is_default) {
            // set latest not expired card to default
            $latestNotExpiredCard = $user->cards()->where('is_default', false)
                ->where('card_expiry_month', '<=', now()->month)
                ->where('card_expiry_year', '<=', now()->year)
                ->latest()
                ->first();
            if ($latestNotExpiredCard) { // set this card as default
                $latestNotExpiredCard->is_default = true;
                $latestNotExpiredCard->save();
            }
        }

        $card_last_four = $card->card_last_four;
        $card_type = $card->card_type;

        // delete card
        $card->delete();

        Log::info('Card removed', [
            'user_id' => $user->id,
            'card_last_four' => $card_last_four,
            'card_type' => $card_type,
        ]);

        return response()->json([
            'message' => __('messages.success.user_settings_controller.Card_removed')
        ]);
    }

    /**
     * Set Card as Default
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group User Settings
     * @subgroup Card
     * @bodyParam card_id integer required The id of the card. Example: 1
     * @response status=200 {
     * "message": "Card set as default"
     * }
     */
    public function postSetCardAsDefault(Request $request)
    {
        $request->validate([
            'card_id' => 'required|exists:user_cards,id'
        ]);

        $user = auth()->user();

        $card = $user->cards()->find($request->card_id);
        if (!$card) {
            return response()->json([
                'message' => __('messages.error.user_settings_controller.No_Cards_found')
                ], 404);
        }

        // make sure all other cards are not default
        $user->cards()->where('is_default', 1)->update(['is_default' => 0]);

        // set card is_default
        $card->is_default = true;
        $card->save();

        return response()->json([
            'message' => __('messages.success.user_settings_controller.Card_set_as_default')
        ]);
    }
}
