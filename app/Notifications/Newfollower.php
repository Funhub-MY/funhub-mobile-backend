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

class Newfollower extends Notification
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
        Log::info('You have new follower ID: ' . $this->follower->id);
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
        ->setData(['follower_id' => (string) $this->follower->id])
        ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
            ->setTitle('New Follower')
            ->setBody($this->follower->name . ' followed you')
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
            'link_to' => $this->follower->id, // if link to url false, means get link_to_object
            'link_to_object' => $this->follower->id, // if link to url false, means get link_to_object
            'action' => 'followed',
            'from' => $this->follower->name,
            'from_id' => $this->follower->id,
            'message' => $this->follower->name . ' followed you'
        ];
    }
}
