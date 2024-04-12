<?php

namespace App\Listeners;

use App\Events\CompletedProfile;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class UserSettingsSavedListener
{
    public $user;
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        // check if name, email, interests, birthday, gender are all saved
        if ($this->user->name
            && $this->user->email
            && $this->user->avatar_url
            && $this->user->articleCategoriesInterests()->count() > 0
            && $this->user->dob)
        {
            Log::info('User profile completed', [
                'user_id' => $this->user->id,
            ]);

            // fire the event
            event(new CompletedProfile($this->user));
        } else {
            Log::info('User profile not completed', [
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'user_email' => $this->user->email,
                'user_avatar' => $this->user->avatar_url,
                'user_interests' => $this->user->articleCategoriesInterests()->count(),
                'user_dob' => $this->user->dob,
            ]);
        }
    }
}
