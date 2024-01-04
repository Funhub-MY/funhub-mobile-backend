<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use App\Models\SystemNotification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class CustomNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $customNotification;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(SystemNotification $customNotification)
    {
        $this->customNotification = $customNotification;
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
                'notification_id' => (string) $this->customNotification->id,
                'notification_type' => (string) $this->customNotification->type,
                'title' => (string) $this->customNotification->title,                
                'content' => (string) $this->customNotification->content,
                'schedule_time' =>  (string) $this->customNotification->scheduled_at,
                'action' => 'custom_notification'
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle($this->customNotification->title)
                ->setBody($this->customNotification->content)
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
            'object' => get_class($this->customNotification),
            'object_id' => $this->customNotification->id,
            'link_to_url' => $this->customNotification->web_link ? true : false,
            'link_to' => $this->customNotification->web_link ? $this->customNotification->web_link : null, // if link to url false, means get link_to_object
            'link_to_object' => $this->customNotification->id, // if link to url false, means get link_to_object
            'action' => 'custom_notification',
            'from' => 'Funhub',
            'from_id' => '',
            'title' => $this->customNotification->title,
            'message' => $this->customNotification->content,
        ];
    }
}
