<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Article;
use Illuminate\Bus\Queueable;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;

use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class TaggedUserInArticle extends Notification
{
    use Queueable;

    protected $article, $user;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Article $article, User $user)
    {
        $this->article = $article;
        $this->user = $user;
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
        return __('messages.notification.fcm.TaggedUserInArticle');
    }

    public function toFcm($notifiable)
    {
        return FcmMessage::create()
            ->setData([
                'object' => (string) get_class($this->article),
                'object_id' => (string) $this->article->id,
                'link_to_url' => (string) 'false',
                'link_to' => (string) $this->article->id, // if link to url false, means get link_to_object
                'link_to_object' => (string) 'null', // if link to url false, means get link_to_object
                'action' => 'tagged_user_in_article',
                'from_name' => (string) $this->user->name,
                'from_id' => (string) $this->user->id,
                'title' => (string) $this->user->name,
                'message' => (string) $this->getMessage(),
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle('有人@你了')
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
            'object' => get_class($this->article),
            'object_id' => $this->article->id,
            'link_to_url' => false,
            'link_to' => $this->article->id, // if link to url false, means get link_to_object
            'link_to_object' => null, // if link to url false, means get link_to_object
            'action' => 'tagged_user_in_article',
            'from_name' => $this->user->name,
            'from_id' => $this->user->id,
            'title' => $this->user->name,
            'message' => $this->getMessage(),
        ];
    }
}
