<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
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
        ->setData([
            'follower_id' => (string) $this->follower->id,
            'action' => 'new_follower'
        ])
        ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
            ->setTitle('新粉丝')
            ->setBody($this->follower->name . '狠狠关注了你，来认识新朋友吧')
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
            'action' => 'followed',
            'from' => $this->follower->name,
            'from_id' => $this->follower->id,
            'title' => $this->follower->name,
            'message' => '狠狠关注了你，来认识新朋友吧'
        ];
    }
}
