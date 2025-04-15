<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;

class AccountRestrictedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $restrictedUntil;
    protected $locale;

    public function __construct($restrictedUntil, $locale = 'en')
    {
        $this->restrictedUntil = $restrictedUntil;
        $this->locale = $locale;
        $this->onQueue('notifications');
    }

    public function via($notifiable)
    {
        return [FcmChannel::class, 'database'];
    }

    public function getTitleAndContent()
    {
        $title = [
            'en' => __('messages.notification.fcm.AccountRestrictedTitle', ['date' => $this->restrictedUntil], 'en'),
            'zh' => __('messages.notification.fcm.AccountRestrictedTitle', ['date' => $this->restrictedUntil], 'zh'),
        ];
        $content = [
            'en' => __('messages.notification.fcm.AccountRestrictedBody', ['date' => $this->restrictedUntil], 'en'),
            'zh' => __('messages.notification.fcm.AccountRestrictedBody', ['date' => $this->restrictedUntil], 'zh'),
        ];
        return [
            'title' => $title[$this->locale] ?? $title['en'],
            'content' => $content[$this->locale] ?? $content['en'],
        ];
    }

    public function toFcm($notifiable)
    {
        $data = [
            'title' => (string) $this->getTitleAndContent()['title'],
            'message' => (string) $this->getTitleAndContent()['content'],
            'redirect' => '#',
            'object' => (string) $notifiable->id,
            'object_id' => (string) $notifiable->id,
            'link_to_url' => 'false',
            'link_to' => '#',
            'link_to_object' => (string) $notifiable->id,
            'action' => 'account_restricted',
            'restricted_until' => (string) $this->restrictedUntil,
            'schedule_time' => '',
            'from_name' => 'Funhub',
            'from_id' => '',
        ];
        return FcmMessage::create()
            ->setData($data)
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle($this->getTitleAndContent()['title'])
                ->setBody($this->getTitleAndContent()['content'])
            );
    }

    public function toArray($notifiable)
    {
        return [
            'title' => (string) $this->getTitleAndContent()['title'],
            'message' => (string) $this->getTitleAndContent()['content'],
            'redirect' => '#',
            'object' => (string) $notifiable->id,
            'object_id' => (string) $notifiable->id,
            'link_to_url' => false,
            'link_to' => '#',
            'link_to_object' => (string) $notifiable->id,
            'action' => 'account_restricted',
            'restricted_until' => (string) $this->restrictedUntil,
            'schedule_time' => '',
            'from_name' => 'Funhub',
            'from_id' => '',
        ];
    }
}
