<?php

namespace App\Observers;

use Exception;
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
     * @param SupportRequestMessage $supportRequestMessage
     * @return void
     */
    public function created(SupportRequestMessage $supportRequestMessage)
    {
        // dont notifty if not production
        if (config('app.env') != 'production') {
            // log
            Log::info('SupportRequestMessage created but not in production environment, skipping notification', ['supportRequestMessage' => $supportRequestMessage]);
            return;
        }

        // if admin created message, notify requestor via FCM
        if ($supportRequestMessage->user->hasRole('super_admin')) {
            try {
                $locale = $supportRequestMessage->request->requestor->last_lang ?? config('app.locale');
                $supportRequestMessage->request->requestor->notify((new NewSupportRequestMessage($supportRequestMessage))->locale($locale));
            } catch (Exception $e) {
                Log::error('Error sending notification: ' . $e->getMessage());
            }
        } else {
            // if NOT admin created message
            // Determine the admin email address based on the request category type
            // $supportEmail = ($supportRequestMessage->request->category['type'] === 'complain') ? config('app.support_email2') : config('app.support_email1');

			$categoryType = $supportRequestMessage->request->category['type'];
			$supportEmails = [];

			switch ($categoryType) {
				case 'complain':
					$supportEmails = [config('app.support_email2')];
					break;

				case 'bug':
				case 'feature_request':
					$supportEmails = [config('app.support_email1'), config('app.support_email2')];
					break;

				case 'information_update':
					$supportEmails = [config('app.support_email1')];
					break;

				default:
					Log::info("Unknown category type: $categoryType");
					return;
			}

            try {
                // Check if this support_request_id already existed more than once in the SupportRequestMessage table
                $existingMessagesCount = SupportRequestMessage::where('support_request_id', $supportRequestMessage->request->id)->count();

				// If this is the first message of the request, send NewSupportRequestRaised email; if this id existed more than once, send update email to support admin
				$notificationType = $existingMessagesCount === 1 ? 'initial' : 'update';
				foreach ($supportEmails as $email) {
					Notification::route('mail', $email)
						->notify(new NewSupportRequestRaised($supportRequestMessage, $notificationType));
				}
			} catch (Exception $e) {
				Log::error('Error sending support request email to admin: ' . $e->getMessage());
			}
        }
    }

    /**
     * Handle the SupportRequestMessage "updated" event.
     *
     * @param SupportRequestMessage $supportRequestMessage
     * @return void
     */
    public function updated(SupportRequestMessage $supportRequestMessage)
    {
        //
    }

    /**
     * Handle the SupportRequestMessage "deleted" event.
     *
     * @param SupportRequestMessage $supportRequestMessage
     * @return void
     */
    public function deleted(SupportRequestMessage $supportRequestMessage)
    {
        //
    }

    /**
     * Handle the SupportRequestMessage "restored" event.
     *
     * @param SupportRequestMessage $supportRequestMessage
     * @return void
     */
    public function restored(SupportRequestMessage $supportRequestMessage)
    {
        //
    }

    /**
     * Handle the SupportRequestMessage "force deleted" event.
     *
     * @param SupportRequestMessage $supportRequestMessage
     * @return void
     */
    public function forceDeleted(SupportRequestMessage $supportRequestMessage)
    {
        //
    }
}
