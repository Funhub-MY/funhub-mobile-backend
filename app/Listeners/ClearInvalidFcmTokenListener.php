<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Facades\Log;
use NotificationChannels\Fcm\FcmChannel;
use Throwable;

class ClearInvalidFcmTokenListener
{
    /**
     * When FCM returns "Requested entity was not found", the token is invalid
     * (expired, app uninstalled, or device unregistered). Clear it so we stop
     * sending to that token. The client must send a new token when the app
     * gets one from Firebase (e.g. on launch or onTokenRefresh).
     *
     * Note: FcmChannel dispatches NotificationFailed with the underlying
     * Kreait\Firebase\Exception\MessagingException in $event->data['exception'],
     * not CouldNotSendNotification, so we check the exception message for any
     * throwable.
     *
     * @param  NotificationFailed  $event
     * @return void
     */
    public function handle(NotificationFailed $event): void
    {
        if ($event->channel !== FcmChannel::class) {
            return;
        }

        $exception = $event->data['exception'] ?? null;
        if (! $exception instanceof Throwable) {
            return;
        }

        $message = $exception->getMessage();
        $previous = method_exists($exception, 'getPrevious') ? $exception->getPrevious() : null;
        if ($previous instanceof Throwable) {
            $message = $message . ' ' . $previous->getMessage();
        }
        if (strpos($message, 'Requested entity was not found') === false
            && strpos($message, 'entity was not found') === false) {
            return;
        }

        $notifiable = $event->notifiable;
        if (! $notifiable instanceof User) {
            return;
        }

        if (empty($notifiable->fcm_token)) {
            return;
        }

        Log::info('Clearing invalid FCM token for user', [
            'user_id' => $notifiable->id,
            'reason' => 'FCM returned "Requested entity was not found"',
        ]);

        $notifiable->update(['fcm_token' => null]);
    }
}
