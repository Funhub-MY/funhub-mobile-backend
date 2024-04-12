<?php

namespace App\Listeners;

use App\Events\UserReferred;
use App\Models\Reward;
use App\Services\PointService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class UserReferredListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\UserReferred  $event
     * @return void
     */
    public function handle(UserReferred $event)
    {
        $user = $event->user;
        $referredBy = $event->referredBy;

        Log::info('[UserReferredListener] User referred by ' . $referredBy->id . ' to ' . $user->id);

        if (config('app.referral_reward')) { // is on
            // reward both end with 1 funhub
            $pointService = new PointService();
            // get first reward in db
            // seed reward
            $reward = Reward::create([
                'name' => '饭盒FUNHUB',
                'description' => '饭盒FUNHUB',
                'points' => 1, // current 1 reward is 1 of value
                'user_id' => 1
            ]);

            $reward = Reward::first();
            $pointService->credit($reward, $user, 1, 'Referral Reward', 'Referral Reward');
            $pointService->credit($reward, $referredBy, 1, 'Referral Reward', 'Referral Reward');

            Log::info('[UserReferredListener] User referred by ' . $referredBy->id . ' to ' . $user->id . ' rewarded with 1 funhub each.');
        }
    }
}
