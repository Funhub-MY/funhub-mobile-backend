<?php

namespace App\Listeners;

use App\Events\CompletedProfile;
use App\Models\ArticleCategory;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use App\Events\UserSettingsUpdated;
use Illuminate\Support\Facades\DB;

class UserSettingsSavedListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(UserSettingsUpdated $event)
    {
        $user = $event->user;
        $userInterestCategoriesCount = DB::table('user_article_categories')
            ->where('user_id', $user->id)->count();

        // check if name, email, interests, birthday, gender are all saved
        if ($user->name
            && $user->email
            && $user->avatar
            && $userInterestCategoriesCount > 0
            && $user->dob)
        {
            Log::info('User profile completed', [
                'user_id' => $user->id,
            ]);

            // fire the event
            event(new CompletedProfile($user));
        } else {
            Log::info('User profile not completed', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'avatar_id' => $user->avatar,
                'user_interests' => $userInterestCategoriesCount,
                'user_dob' => $user->dob,
            ]);
        }
    }
}
