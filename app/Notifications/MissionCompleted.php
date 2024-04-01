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

    protected $mission, $user, $reward, $rewardQuantity;

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

        // Determine the locale based on the user's last_lang or use the system default
        $locale = $user->last_lang ?? config('app.locale');

        // Set the locale for this notification
        app()->setLocale($locale);
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
                'mission_id' => (string) $this->mission->id,
                'user_id' => (string) $this->user->id,
                'action' => 'mission_completed'
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle('任务完成')
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
            'from' => $this->user->name,
            'from_id' => $this->user->id,
            'title' => '恭喜完成任务',
            'message' => $this->getMessage(),
        ];
    }
}
