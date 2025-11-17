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
    public $mission;

    public function __construct($reward, $rewardQuantity, $user, $missionName, $mission = null)
    {
        $this->reward = $reward;
        $this->rewardQuantity = $rewardQuantity;
        $this->user = $user;
        $this->missionName = $missionName;
        $this->mission = $mission;
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
        $rewardName = $this->reward->name;
        $rewardQuantity = $this->rewardQuantity;
        $missionName = $this->missionName;

        $rewardBodyKey = ($rewardName === '饭盒FUNHUB') ? 'messages.notification.fcm.RewardReceivedBodyFunbox' : 'messages.notification.fcm.RewardReceivedBody';

        return FcmMessage::create()
            ->setData([
                'object' => (string) get_class($this->reward),
                'object_id' => (string) $this->reward->id,
                'link_to_url' => (string) 'false',
                'link_to' => (string) $this->reward->id, // if link to url false, means get link_to_object
                'link_to_object' => (string) 'false', // if link to url false, means get link_to_object
                'action' => 'mission_rewarded',
                'from_name' => 'Funhub',
                'from_id' => '',
                'title' => (string) $this->user->name,
                'message' => __($rewardBodyKey, [
                    'rewardName' => $rewardName,
                    'rewardQuantity' => $rewardQuantity,
                    'missionName' => $missionName
                ])
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle(__('messages.notification.fcm.RewardReceivedTitle', compact('rewardName', 'rewardQuantity', 'missionName')))
                ->setBody(__($rewardBodyKey, compact('rewardName', 'rewardQuantity', 'missionName')))
            );
    }


    public function toArray($notifiable)
    {
        $rewardName = $this->reward->name;
        $rewardQuantity = $this->rewardQuantity;
        $missionName = $this->missionName;

        $rewardReceivedTitle = __('messages.notification.fcm.RewardReceivedTitle', compact('rewardName', 'rewardQuantity', 'missionName'));
        $rewardBodyKey = ($rewardName === '饭盒FUNHUB') ? 'messages.notification.database.RewardReceivedBodyFunbox' : 'messages.notification.database.RewardReceivedBody';

        return [
            'object' => get_class($this->reward),
            'object_id' => $this->reward->id,
            'link_to_url' => false,
            'link_to' => $this->reward->id, // if link to url false, means get link_to_object
            'link_to_object' => false, // if link to url false, means get link_to_object
            'action' => 'mission_rewarded',
            'from_name' => 'Funhub',
            'from_id' => '',
//            'title' => $this->user->name,
            'title' => $rewardReceivedTitle,
            'message' => __($rewardBodyKey, [
                'rewardName' => $rewardName,
                'rewardQuantity' => $rewardQuantity,
                'missionName' => $missionName
            ]),
            'extra' => [
                'reward_id' => $this->reward->id,
                'user_id' => $this->user->id,
                'mission_id' => $this->mission->id,
                'requires_self_claim' => ( $this->mission->auto_disburse_rewards) ? false : true, // if auto disburse means no need self claim
            ]
        ];
    }
}

