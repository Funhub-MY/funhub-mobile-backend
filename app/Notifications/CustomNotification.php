<?php

namespace App\Notifications;

use App\Models\Article;
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
    protected $userLocale;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(SystemNotification $customNotification, $userLocale = null)
    {
        $this->customNotification = $customNotification;
        $this->userLocale = $userLocale ?? config('app.locale');
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

    private function getTitleAndContent()
    {
        $title = $this->customNotification->title;
        try {
            $title = json_decode($this->customNotification->title);
            // based on userLocale return correct title {en: 'abc', zh: 'xyz'}
            $title = $title->{$this->userLocale} ?? $this->customNotification->title;
        } catch (\Exception $e) {
            $title = $this->customNotification->title;
        }
        // check if title and content is json array by attempting decoding it first
        $content = $this->customNotification->content;
        try {
            $content = json_decode($this->customNotification->content);
            // based on userLocale return correct title {en: 'abc', zh: 'xyz'}
            $content = $content->{$this->userLocale} ?? $this->customNotification->content;
        } catch (\Exception $e) {
            $content = $this->customNotification->content;
        }

        return [
            'title' => $title,
            'content' => $content
        ];
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
                'title' => (string) $this->getTitleAndContent()['title'],
                'content' => (string) $this->getTitleAndContent()['content'],
                'schedule_time' =>  (string) $this->customNotification->scheduled_at,
                'action' => 'custom_notification'
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle($this->getTitleAndContent()['title'])
                ->setBody($this->getTitleAndContent()['content'])
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
        $toArrayData = [
            'object' => get_class($this->customNotification), // App/Models/SystemNotification
            'object_id' => $this->customNotification->id,
            'link_to_url' => $this->customNotification->web_link ? true : false,
            'link_to' => $this->customNotification->web_link ? $this->customNotification->web_link : null, // if link to url false, means get link_to_object
            'link_to_object' => $this->customNotification->id, // if link to url false, means get link_to_object
            'action' => 'custom_notification',
            'from' => 'Funhub',
            'from_id' => '',
            'title' => (string) $this->getTitleAndContent()['title'],
            'message' => (string) $this->getTitleAndContent()['content'],
        ];

        // Check if redirect type is dynamic and content type is set
        if ($this->customNotification->redirect_type == SystemNotification::REDIRECT_DYNAMIC && $this->customNotification->content_type) {
            // Set the class of the 'object' based on the content type
            $toArrayData['object'] = $this->customNotification->content_type; // App\Models\Article / User / MerchantOffer
            $toArrayData['object_id'] = $this->customNotification->content_id; // article_id, user_id, offer_id

            if ($this->customNotification->content_type == Article::class) {
                $toArrayData['article_type'] = $this->customNotification->content->type;
            }
        }

        return $toArrayData;
    }
}
