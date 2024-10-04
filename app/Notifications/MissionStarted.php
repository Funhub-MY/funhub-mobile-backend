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

class MissionStarted extends Notification
{
    protected $mission, $user, $currentProgress, $goals, $translatedMissionName;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Mission $mission, User $user, $currentProgress, $goals)
    {
        $this->mission = $mission;
        $this->user = $user;
        $this->currentProgress = $currentProgress;
        $this->goals = $goals;

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
        return __('messages.notification.fcm.MissionStarted', [
            'missionName' => $this->mission->name
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
                'action' => 'mission_started',
                'from_name' => (string) $this->user->name,
                'from_id' => (string) $this->user->id,
                'title' => (string) $this->translatedMissionName,
                'message' => (string) $this->getMessage(),
                'extra' => json_encode([
                    'goals' => (string) $this->goals,
                    'current_progress' => (string) $this->currentProgress,
                    'complete_mission_image_en_url' => $this->mission->getFirstMediaUrl(Mission::COMPLETED_MISSION_COLLECTION_EN),
                    'complete_mission_image_zh_url' => $this->mission->getFirstMediaUrl(Mission::COMPLETED_MISSION_COLLECTION_ZH),
                    'frequency' => (string ) $this->mission->frequency,
                ])
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle(__('messages.notification.fcm.MissionStartedTitle'))
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
            'action' => 'mission_started',
            'from_name' => $this->user->name,
            'from_id' => $this->user->id,
            'title' => $this->translatedMissionName,
            'message' => $this->getMessage(),
            'extra' => [
                'goals' => $this->goals,
                'current_progress' => $this->currentProgress,
                'complete_mission_image_en_url' => $this->mission->getFirstMediaUrl(Mission::COMPLETED_MISSION_COLLECTION_EN),
                'complete_mission_image_zh_url' => $this->mission->getFirstMediaUrl(Mission::COMPLETED_MISSION_COLLECTION_ZH),
                'frequency' => $this->mission->frequency,
            ]
        ];
    }
}
