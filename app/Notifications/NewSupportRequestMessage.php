<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use App\Models\SupportRequestMessage;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NewSupportRequestMessage extends Notification implements ShouldQueue
{
    use Queueable;

    protected $message;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(SupportRequestMessage $message)
    {
        $this->message = $message;
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
            'support_request_id' => (string) $this->message->request->id,
            'support_request_message_id' => (string) $this->message->id,
            'action' => 'new_support_request_message'
        ])
        ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
            ->setTitle('申诉新消息')
            ->setBody($this->message->request->requestor->name . '您的申诉有一条新消息')
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
            'object' => get_class($this->message),
            'object_id' => $this->message->id,
            'link_to_url' => false,
            'link_to' => '', // if link to url false, means get link_to_object
            'link_to_object' => $this->message->id, // if link to url false, means get link_to_object
            'action' => 'new_support_request_message_received',
            'from' => '小饭',
            'from_id' => $this->message->user->id,
            'title' => $this->message->user->name,
            'message' => '您的申诉有一条新消息，快去看看吧',
        ];
    }
}
