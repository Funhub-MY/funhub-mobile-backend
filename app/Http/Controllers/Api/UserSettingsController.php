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
}
