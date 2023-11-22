<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Models\SupportRequestMessage;
use Illuminate\Support\Facades\Notification;
use App\Notifications\NewSupportRequestRaised;
use App\Notifications\NewSupportRequestMessage;

class SupportRequestMessageObserver
{
    /**
     * Handle the SupportRequestMessage "created" event.
     *
     * @param  \App\Models\SupportRequestMessage  $supportRequestMessage
     * @return void
     */
    public function created(SupportRequestMessage $supportRequestMessage)
    {
        // if admin created message, notify requestor via FCM 
        if ($supportRequestMessage->user->hasRole('super_admin')) { 
            try {
                $supportRequestMessage->request->requestor->notify(new NewSupportRequestMessage($supportRequestMessage));
            } catch (\Exception $e) {
                Log::error('Error sending notification: ' . $e->getMessage());
            }
        } else {
            // if NOT admin created message && if this is the first message of the request, send email based on request category type
            // Determine the admin email address based on the request category type
            $supportEmail = ($supportRequestMessage->request->category['type'] === 'complain') ? config('app.support_email2') : config('app.support_email1');

            try {
                Notification::route('mail', $supportEmail)
                    ->notify(new NewSupportRequestRaised($supportRequestMessage));
            } catch (\Exception $e) {
                Log::error('Error sending support request email to admin: ' . $e->getMessage());
            }
        }
    }

    /**
     * Handle the SupportRequestMessage "updated" event.
     *
     * @param  \App\Models\SupportRequestMessage  $supportRequestMessage
     * @return void
     */
    public function updated(SupportRequestMessage $supportRequestMessage)
    {
        //
    }

    /**
     * Handle the SupportRequestMessage "deleted" event.
     *
     * @param  \App\Models\SupportRequestMessage  $supportRequestMessage
     * @return void
     */
    public function deleted(SupportRequestMessage $supportRequestMessage)
    {
        //
    }

    /**
     * Handle the SupportRequestMessage "restored" event.
     *
     * @param  \App\Models\SupportRequestMessage  $supportRequestMessage
     * @return void
     */
    public function restored(SupportRequestMessage $supportRequestMessage)
    {
        //
    }

    /**
     * Handle the SupportRequestMessage "force deleted" event.
     *
     * @param  \App\Models\SupportRequestMessage  $supportRequestMessage
     * @return void
     */
    public function forceDeleted(SupportRequestMessage $supportRequestMessage)
    {
        //
    }
}
