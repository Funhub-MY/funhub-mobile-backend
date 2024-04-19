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
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toFcm($notifiable)
    {
        return FcmMessage::create()
            ->setData([
                'action' => 'custom_deal_notification'
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
            'action' => 'custom_deal_notification',
            'from' => 'Funhub',
            'from_id' => '',
            'title' => $this->referral->name,
            'message' => __('messages.notification.database.ReferralRewardReceivedBody', [
                'name' => $this->referral->name,
            ]),
        ];
    }
}

