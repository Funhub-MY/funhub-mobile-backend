<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;

class AccountUnrestrictedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $locale;

    public function __construct($locale = 'en')
    {
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
            'en' => __('messages.notification.fcm.AccountUnrestrictedTitle', [], 'en'),
            'zh' => __('messages.notification.fcm.AccountUnrestrictedTitle', [], 'zh'),
        ];
        $content = [
            'en' => __('messages.notification.fcm.AccountUnrestrictedBody', [], 'en'),
            'zh' => __('messages.notification.fcm.AccountUnrestrictedBody', [], 'zh'),
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
            'link_to_url' => 'true',
            'link_to' => '#',
            'link_to_object' => (string) $notifiable->id,
            'action' => 'account_restricted_reactivated',
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
            'link_to_url' => true,
            'link_to' => '#',
            'link_to_object' => (string) $notifiable->id,
            'action' => 'account_restricted_reactivated',
            'schedule_time' => '',
            'from_name' => 'Funhub',
            'from_id' => '',
        ];
    }
}
