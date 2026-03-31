<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Kreait\Firebase\Exception\Messaging\NotFound as FcmTokenNotFound;
use Kreait\Firebase\Exception\MessagingException;
use NotificationChannels\Fcm\Exceptions\CouldNotSendNotification;
use NotificationChannels\Fcm\FcmChannel;

/**
 * Extends the package FCM channel so invalid/expired device tokens do not fail
 * queued notification jobs. The parent dispatches NotificationFailed first
 * (ClearInvalidFcmTokenListener clears fcm_token); we then complete without throwing.
 */
class TolerantFcmChannel extends FcmChannel
{
    /**
     * @param  mixed  $notifiable
     * @return array
     */
    public function send($notifiable, Notification $notification)
    {
        try {
            return parent::send($notifiable, $notification);
        } catch (CouldNotSendNotification $e) {
            $previous = $e->getPrevious();
            if ($previous instanceof MessagingException && $this->isUnregisteredOrMissingToken($previous)) {
                return [];
            }

            throw $e;
        }
    }

    private function isUnregisteredOrMissingToken(MessagingException $exception): bool
    {
        if ($exception instanceof FcmTokenNotFound) {
            return true;
        }

        $message = $exception->getMessage();

        return str_contains($message, 'Requested entity was not found')
            || str_contains($message, 'entity was not found');
    }
}
