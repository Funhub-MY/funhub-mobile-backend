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

    public $reward;
    public $rewardQuantity;
    public $user;
    public $missionName;

    public function __construct($reward, $rewardQuantity, $user, $missionName)
    {
        $this->reward = $reward;
        $this->rewardQuantity = $rewardQuantity;
        $this->user = $user;
        $this->missionName = $missionName;
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
        $rewardName = $this->reward->name;
        $rewardQuantity = $this->rewardQuantity;
        $missionName = $this->missionName;

        return FcmMessage::create()
            ->setData([
                'action' => 'custom_notification'
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle(__('messages.notification.fcm.RewardReceivedTitle', compact('rewardName', 'rewardQuantity', 'missionName')))
                ->setBody(__('messages.notification.fcm.RewardReceivedBody', compact('rewardName', 'rewardQuantity', 'missionName')))
            );
    }


    public function toArray($notifiable)
    {
        $rewardName = $this->reward->name;
        $rewardQuantity = $this->rewardQuantity;
        $missionName = $this->missionName;

        return [
            'object' => get_class($this->reward),
            'object_id' => $this->reward->id,
            'link_to_url' => false,
            'link_to' => $this->reward->id, // if link to url false, means get link_to_object
            'link_to_object' => false, // if link to url false, means get link_to_object
            'action' => 'custom_notification',
            'from' => 'Funhub',
            'from_id' => '',
            'title' => $this->user->name,
            'message' => __('messages.notification.database.RewardReceivedBody', [
                'rewardName' => $rewardName,
                'rewardQuantity' => $rewardQuantity,
                'missionName' => $missionName
            ])
        ];
    }
}

