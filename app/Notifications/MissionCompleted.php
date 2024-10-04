<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Mission;
use Illuminate\Bus\Queueable;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class MissionCompleted extends Notification
{

    protected $mission, $user, $reward, $rewardQuantity, $translatedMissionName;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Mission $mission, User $user, $reward, $rewardQuantity)
    {
        $this->mission = $mission;
        $this->user = $user;
        $this->reward = $reward;
        $this->rewardQuantity = $rewardQuantity;

        if ($this->mission) {
            $locale = $this->user->last_lang ?? config('app.locale');
            $this->translatedMissionName = $this->mission->name;
            if (isset($this->mission->name_translation)) {
                $this->translatedMissionName = json_decode($this->mission->name_translation, true);
                if ($this->translatedMissionName && isset($this->translatedMissionName[$locale])) {
                    $this->translatedMissionName = $this->translatedMissionName[$locale];
                } else {
                    $this->translatedMissionName = $this->translatedMissionName[config('app.locale')];
                }
            }
        }
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return [FcmChannel::class, 'database'];
    }

    protected function getMessage()
    {
        return __('messages.notification.fcm.MissionCompleted', [
            'missionName' => $this->mission->name,
            'reward' => $this->reward,
            'rewardQuantity' => $this->rewardQuantity
        ]);
    }

    public function toFcm($notifiable)
    {
        return FcmMessage::create()
            ->setData([
                'object' => (string) get_class($this->mission),
                'object_id' => (string) $this->mission->id,
                'link_to_url' => (string) 'false',
                'link_to' => (string) $this->mission->id, // if link to url false, means get link_to_object
                'link_to_object' => (string) 'null', // if link to url false, means get link_to_object
                'action' => 'mission_completed',
                'from_name' => (string) $this->user->name,
                'from_id' => (string) $this->user->id,
                'title' => (string) $this->translatedMissionName,
                'message' => (string) $this->getMessage(),
                'extra' => json_encode([
                    'complete_mission_image_en_url' => $this->mission->getFirstMediaUrl(Mission::COMPLETED_MISSION_COLLECTION_EN),
                    'complete_mission_image_zh_url' => $this->mission->getFirstMediaUrl(Mission::COMPLETED_MISSION_COLLECTION_ZH),
                    'frequency' => $this->mission->frequency,
                ])
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle(__('messages.notification.fcm.MissionCompletedTitle'))
                ->setBody($this->getMessage())
            );
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'object' => get_class($this->mission),
            'object_id' => $this->mission->id,
            'link_to_url' => false,
            'link_to' => $this->mission->id, // if link to url false, means get link_to_object
            'link_to_object' => null, // if link to url false, means get link_to_object
            'action' => 'mission_completed',
            'from_name' => $this->user->name,
            'from_id' => $this->user->id,
            'title' => $this->translatedMissionName,
            'message' => $this->getMessage(),
            'extra' => [
                'complete_mission_image_en_url' => $this->mission->getFirstMediaUrl(Mission::COMPLETED_MISSION_COLLECTION_EN),
                'complete_mission_image_zh_url' => $this->mission->getFirstMediaUrl(Mission::COMPLETED_MISSION_COLLECTION_ZH),
                'frequency' => $this->mission->frequency,
            ]
        ];
    }
}
