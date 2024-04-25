<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;

class Newfollower extends Notification implements ShouldQueue
{
    use Queueable;
    protected $follower;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(User $follower)
    {
        $this->follower = $follower;
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

    public function toFcm($notifiable)
    {
        return FcmMessage::create()
        // ->setData([
        //     'object' => (string) get_class($this->follower), // UserFollowing model
        //     'object_id' => (string) $this->follower->id, // record id
        //     'link_to_url' => (string) 'false',
        //     'link_to' => '', // if link to url false, means get link_to_object
        //     'link_to_object' => (string) $this->follower->id, // if link to url false, means get link_to_object
        //     'follower_id' => (string) $this->follower->id,
        //     'action' => 'new_follower',
        //     'from' => (string) $this->follower->name,
        //     'from_id' => (string) $this->follower->id,
        //     'title' => (string) $this->follower->name,
        //     'message' => __('messages.notification.database.Newfollower')
        // ])
        ->setData([
            'data' => [
                'object' => (string) get_class($this->follower), // UserFollowing model
                'object_id' => (string) $this->follower->id, // record id
                'link_to_url' => (string) 'false',
                'link_to' => '', // if link to url false, means get link_to_object
                'link_to_object' => (string) $this->follower->id, // if link to url false, means get link_to_object
                'follower_id' => (string) $this->follower->id,
                'action' => 'new_follower',
                'from' => (string) $this->follower->name,
                'from_id' => (string) $this->follower->id,
                'title' => (string) $this->follower->name,
                'message' => __('messages.notification.database.Newfollower')
            ],
        ])
        ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
            ->setTitle('新粉丝')
            ->setBody(__('messages.notification.fcm.Newfollower', ['followerName' => $this->follower->name]))
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
            'object' => get_class($this->follower), // UserFollowing model
            'object_id' => $this->follower->id, // record id
            'link_to_url' => false,
            'link_to' => '', // if link to url false, means get link_to_object
            'link_to_object' => $this->follower->id, // if link to url false, means get link_to_object
            'follower_id' => (string) $this->follower->id,
            'action' => 'new_follower',
            'from' => $this->follower->name,
            'from_id' => $this->follower->id,
            'title' => $this->follower->name,
            'message' => __('messages.notification.database.Newfollower')
        ];
    }
}
