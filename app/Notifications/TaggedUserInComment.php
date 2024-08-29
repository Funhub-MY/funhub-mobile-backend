<?php

namespace App\Notifications;

use App\Models\Article;
use App\Models\User;
use App\Models\Comment;
use Illuminate\Bus\Queueable;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class TaggedUserInComment extends Notification
{
    use Queueable;

    protected $comment, $user;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Comment $comment, User $user)
    {
        $this->comment = $comment;
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
        return __('messages.notification.fcm.TaggedUserInComment');
    }

    public function toFcm($notifiable)
    {
        return FcmMessage::create()
            ->setData([
                'object' => (string) get_class($this->comment),
                'object_id' => (string) $this->comment->id,
                'article_id' => ($this->comment->commentable_type == Article::class) ? (string) $this->comment->commentable->id : null,
                'article_type' => ($this->comment->commentable_type == Article::class) ? (string) $this->comment->commentable->type : null,
                'link_to_url' => (string) 'false',
                'link_to' => (string) $this->comment->id, // if link to url false, means get link_to_object
                'link_to_object' => (string) 'null', // if link to url false, means get link_to_object
                'action' => 'tagged_user_in_comment',
                'from_name' => (string) $this->user->name,
                'from_id' => (string) $this->user->id,
                'title' => (string) $this->user->name,
                'message' => (string) $this->getMessage(),
                'extra' => json_encode([
                    'parent_id' => ($this->comment->parent_id) ? (string) $this->comment->parent_id : null,
                    'reply_to_id' => ($this->comment->reply_to_id) ? (string) $this->comment->reply_to_id : null,
                    'comment_id' => (string) $this->comment->id,
                ])
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle(__('messages.notification.fcm.TaggedUser'))
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
            'object' => get_class($this->comment),
            'object_id' => $this->comment->id,
            'link_to_url' => false,
            'link_to' => $this->comment->id, // if link to url false, means get link_to_object
            'link_to_object' => null, // if link to url false, means get link_to_object
            'action' => 'tagged_user_in_comment',
            'from_name' => $this->user->name,
            'from_id' => $this->user->id,
            'title' => $this->user->name,
            'message' => $this->getMessage(),
            'extra' => [
                'parent_id' => ($this->comment->parent_id) ? $this->comment->parent_id : null,
                'reply_to_id' => ($this->comment->reply_to_id) ? $this->comment->reply_to_id : null,
                'comment_id' => $this->comment->id,
            ]
        ];
    }
}
