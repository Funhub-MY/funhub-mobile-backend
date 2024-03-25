<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserSettingsRequest;
use App\Models\ArticleCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserSettingsController extends Controller
{
    /**
     * Get settings of logged in user
     *
     * @return \Illuminate\Http\JsonResponse
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
     * @return \Illuminate\Http\JsonResponse
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
            'email' => 'required|email|unique:users,email,' . auth()->user()->id,
        ]);

        // if user email still same with current email then reject
        if ($request->has('email') && auth()->user()->email == $request->email) {
            return response()->json(['message' => __('messages.error.user_settings_controller.Email_already_verified_for_your_account')], 422);
        }

        $user = auth()->user();
        $user->email = $request->email;
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
        return response()->json(['message' => __('messages.success.user_settings_controller.Email_Verified')], 200);
    }

    /**
     * Update User Name
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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

        return response()->json([
            'message' => __('messages.success.user_settings_controller.Name_updated'),
            'name' => $user->name,
        ]);
    }

    /**
     * Update User Username
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
            'username' => 'required|string|max:9|unique:users,username,' . auth()->user()->id,
        ]);

        $user = auth()->user();
        $user->username = $request->username;
        $user->save();

        return response()->json([
            'message' => __('messages.success.user_settings_controller.Username_updated'),
            'username' => $user->username,
        ]);
    }

    /**
     * Update User Bio
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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

        return response()->json([
            'message' => __('messages.success.user_settings_controller.Bio_updated'),
            'bio' => $user->bio,
        ]);
    }

    /**
     * Update User Job title
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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

        return response()->json([
            'message' => __('messages.success.user_settings_controller.Job_Title_updated'),
            'job_title' => $user->job_title,
        ]);
    }

    /**
     * Update User Date of Birth
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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

        return response()->json([
            'message' => __('messages.success.user_settings_controller.Date_of_birth_updated'),
            'dob' => $user->dob,
        ]);
    }

    /**
     * Update User Save Gender
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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

        return response()->json([
            'message' => __('messages.success.user_settings_controller.Gender_updated'),
            'gender' => $user->gender
        ]);
    }

    /**
     * Update User Location
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
     * @return \Illuminate\Http\JsonResponse
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
        if ($categories->where('parent_id', '!=', null)->count()) {
            $request->category_ids = array_merge($request->category_ids, $categories->where('parent_id', '!=', null)->pluck('parent_id')->toArray());
        }

        $user = auth()->user();

        // check if article category ids exists only sync
        $user->articleCategoriesInterests()->sync($request->category_ids);
        return response()->json([
            'message' => __('messages.success.user_settings_controller.Article_categories_linked_to_user'),
            'category_ids' => $user->articleCategoriesInterests->pluck('id')->toArray(),
        ]);
    }

    /**
     * Upload or Update User Profile Picture
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
     * @return \Illuminate\Http\JsonResponse
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
     * @param  \Illuminate\Http\Request  $request
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
     * @return \Illuminate\Http\JsonResponse
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
                'message' => __('messages.success.user_settings_controller.Profile_privacy_already_set_to', $request->profile_privacy),
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
}
