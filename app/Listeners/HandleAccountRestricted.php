<?php

namespace App\Listeners;

use App\Events\OnAccountRestricted;
use App\Notifications\CustomNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;

class HandleAccountRestricted
{
    /**
     * Handle the event.
     */
    public function handle(OnAccountRestricted $event)
    {
        $user = $event->user;
        $restricted = $event->newRestricted;
        $restrictedUntil = $event->newRestrictedUntil;
        $locale = $user->locale ?? 'en';

        if ($restricted) {
            $user->notify(new \App\Notifications\AccountRestrictedNotification($restrictedUntil, $locale));
        } else {
            $user->notify(new \App\Notifications\AccountUnrestrictedNotification($locale));
        }
    }
}
