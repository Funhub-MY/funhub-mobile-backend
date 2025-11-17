<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;

class ReferralRewardReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $user;
    public $referral;

    public function __construct($user, $referral)
    {
        $this->user = $user;
        $this->referral = $referral;
    }

    public function via($notifiable)
    {
        return [FcmChannel::class, 'database'];
    }

     /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return MailMessage
     */
    public function toFcm($notifiable)
    {
        return FcmMessage::create()
            ->setData([
                'object' => (string) get_class($this->referral),
                'object_id' => (string) $this->referral->id,
                'link_to_url' => (string) 'false',
                'link_to' => (string) $this->referral->id, // if link to url false, means get link_to_object
                'link_to_object' => (string) 'false', // if link to url false, means get link_to_object
                'action' => 'custom_notification',
                'from_name' => 'Funhub',
                'from_id' => '',
                'title' => (string) $this->referral->name,
                'message' => __('messages.notification.database.ReferralRewardReceivedBody', [
                    'name' => $this->referral->name,
                ]),
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle(__('messages.notification.fcm.ReferralRewardReceivedTitle'))
                ->setBody(__('messages.notification.fcm.ReferralRewardReceivedBody', [
                    'name' => $this->referral->name,
                ]))
            );
    }


    public function toArray($notifiable)
    {
        return [
            'object' => get_class($this->referral),
            'object_id' => $this->referral->id,
            'link_to_url' => false,
            'link_to' => $this->referral->id, // if link to url false, means get link_to_object
            'link_to_object' => false, // if link to url false, means get link_to_object
            'action' => 'custom_notification',
            'from_name' => 'Funhub',
            'from_id' => '',
            'title' => $this->referral->name,
            'message' => __('messages.notification.database.ReferralRewardReceivedBody', [
                'name' => $this->referral->name,
            ]),
        ];
    }
}

