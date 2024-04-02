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
                $locale = $supportRequestMessage->request->requestor->last_lang ?? config('app.locale');
                $supportRequestMessage->request->requestor->notify((new NewSupportRequestMessage($supportRequestMessage))->locale($locale));
            } catch (\Exception $e) {
                Log::error('Error sending notification: ' . $e->getMessage());
            }
        } else {
            // if NOT admin created message
            // Determine the admin email address based on the request category type
            $supportEmail = ($supportRequestMessage->request->category['type'] === 'complain') ? config('app.support_email2') : config('app.support_email1');

            try {
                // Check if this support_request_id already existed more than once in the SupportRequestMessage table
                $existingMessagesCount = SupportRequestMessage::where('support_request_id', $supportRequestMessage->request->id)->count();

                if ($existingMessagesCount === 1) { // If this is the first message of the request, send NewSupportRequestRaised email
                    Notification::route('mail', $supportEmail)
                        ->notify(new NewSupportRequestRaised($supportRequestMessage));
                } elseif ($existingMessagesCount > 0) { // If this id existed more than once, send update email to support admin
                    Notification::route('mail', $supportEmail)
                        ->notify(new NewSupportRequestRaised($supportRequestMessage, 'update'));
                }
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
