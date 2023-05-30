<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\UserFollowing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;

class Userfollowed extends Notification
{
    use Queueable;

    protected $user;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user = $user;
        //
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
        ->setData(['follower_id' => $this->user->id])
        ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create())
        ->setTitle(
           'New Follower'
        )
        ->setBody($this->user->name . ' followed you');
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
            'object' => get_class($this->user), // UserFollowing model
            'object_id' => $this->user->id, // record id
            'link_to_url' => false,
            'link_to' => $this->user->id, // if link to url false, means get link_to_object
            'link_to_object' => $this->user, // if link to url false, means get link_to_object
            'action' => 'followed',
            'from' => $this->user->name,
            'from_id' => $this->user->id,
            'message' => $this->user->name . ' followed you'
        ];
    }
}
