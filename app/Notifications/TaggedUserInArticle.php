<?php

namespace App\Notifications;

use App\Models\Article;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;

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
        return '在文章中提到了你';
    }

    public function toFcm($notifiable)
    {
        return FcmMessage::create()
            ->setData([
                'article_id' => (string) $this->article->id,
                'from_user_id' => (string) $this->user->id,
                'action' => 'tagged_user_in_article'
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle('提到了你')
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
            'from' => $this->user->name,
            'from_id' => $this->user->id,
            'message' => $this->getMessage(),
        ];
    }
}
