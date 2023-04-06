<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserSettingsRequest;
use Illuminate\Http\Request;

class UserSettingsController extends Controller
{
    /**
     * Get settings of logged in user
     * 
     * @return \Illuminate\Http\JsonResponse
     * 
     * @group User Settings
     * @response status=200 scenario="success" {
     *  "key": "value"
     * }
     * @response status=401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response status=404 scenario="No settings found yet" {"message": "No settings found yet."}
     */
    public function getSettings()
    {
        $settings = auth()->user()->settings;
        if ($settings) {
            return response()->json($settings);
        } else {
            return response()->json(['message' => 'No settings found yet.'], 404);
        }
    }

    /**
     * Update/Create settings of logged in user
     * 
     * @param UserSettingsRequest $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @group User Settings
     * @bodyParam key string required Key of the setting. Example: profile_private
     * @bodyParam value string required Value of the setting. Example: true
     * @response status=200 scenario="success" {
     * "message": "Settings updated"
     * }
     * @response status=401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["key": ["The Key field is required."], "value": ["The Value field is required."] ]}
     */
    public function postSettings(UserSettingsRequest $request)
    {
        $user = auth()->user();
        $user->settings()->updateOrCreate(
            ['key' => $request->key],
            ['value' => $request->value]
        );
        return response()->json(['message' => 'Settings updated']);
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
     * "message": "Email updated"
     * }
     * @response status=401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     */
    public function postSaveEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email,' . auth()->user()->id,
        ]);

        $user = auth()->user();
        $user->email = $request->email;
        $user->save();

        return response()->json(['message' => 'Email updated']);
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
     * "message": "Name updated"
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

        return response()->json(['message' => 'Name updated']);
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
     * "message": "Article categories linked to user"
     * }
     * @response status=401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     */
    public function postLinkArticleCategoriesInterests(Request $request)
    {
        $request->validate([
            'category_ids' => 'required|array',
        ]);

        $user = auth()->user();

        // check if article category ids exists only sync
        $user->articleCategoriesInterests()->sync($request->category_ids);
        return response()->json(['message' => 'Article categories linked to user']);
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
        $uploadedAvatar = $user->addMedia($request->avatar)->toMediaCollection('avatar');

        // save user avatar id
        $user->avatar = $uploadedAvatar->id;
        $user->save();

        return response()->json([
            'message' => 'Avatar uploaded',
            'avatar_id' => $uploadedAvatar->id,
            'avatar' => $uploadedAvatar->getUrl(),
            'avatar_thumb' => $uploadedAvatar->getUrl('thumb'),
        ]);
    }
}
