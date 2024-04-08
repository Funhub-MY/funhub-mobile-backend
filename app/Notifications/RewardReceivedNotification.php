<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;

class RewardReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $rewardName;
    public $rewardQuantity;

    public function __construct($rewardName, $rewardQuantity)
    {
        $this->rewardName = $rewardName;
        $this->rewardQuantity = $rewardQuantity;
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

                'action' => 'mission_completed_reward_disbursement'
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle(__('messages.notification.reward_received_title'))
                ->setBody(__('messages.notification.reward_received_body', compact('rewardName', 'rewardQuantity'))
            );
    }


    public function toArray($notifiable)
    {
        return [
            'object' => 'reward',
            'object_id' => $this->comment->id,
            'link_to_url' => false,
            'link_to' => $this->comment->commentable->id, // if link to url false, means get link_to_object
            'link_to_object' => $this->comment->commentable_type, // if link to url false, means get link_to_object
            'action' => 'commented',
            'from' => $this->comment->user->name,
            'from_id' => $this->comment->user->id,
            'title' => $this->comment->user->name,
            'message' => __('messages.notification.database.Commented'),
        ];
    }
}

