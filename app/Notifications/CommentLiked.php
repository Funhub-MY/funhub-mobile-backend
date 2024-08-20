<?php

namespace App\Notifications;

use App\Models\Article;
use App\Models\User;
use App\Models\Comment;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class CommentLiked extends Notification implements ShouldQueue
{
    use Queueable;

    protected $comment, $user;

    /**
     * Create a new notifi赞了你的评论，觉得超有趣！cation instance.
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

    public function toFcm($notifiable)
    {
        return FcmMessage::create()
            ->setData([
                'object' => (string) get_class($this->comment),
                'object_id' => (string) $this->comment->id,
                'article_id' => ($this->comment->commentable_type == Article::class) ? (string) $this->comment->commentable->id : null,
                'article_type' => ($this->comment->commentable_type == Article::class) ? (string) $this->comment->commentable->type : null,
                'link_to_url' => (string) 'false',
                'link_to' => (string) $this->comment->commentable->id, // if link to url false, means get link_to_object
                'link_to_object' => (string) $this->comment->commentable_type, // if link to url false, means get link_to_object
                'action' => (string) 'comment_liked',
                'from_name' => (string) $this->user->name,
                'from_id' => (string) $this->user->id,
                'title' => (string) $this->user->name,
                'message' => __('messages.notification.database.CommentLiked'),
                'extra' => [
                    'parent_id' => $this->comment->parent_id,
                    'reply_to_id' => $this->comment->reply_to_id,
                    'comment_id' => $this->comment->id,
                ]
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle('探文互动')
                ->setBody(__('messages.notification.fcm.CommentLiked', [
                    'username' => $this->user->name,
                    'comment' => Str::limit($this->comment->body, 10, '...')
                ]))
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
            'link_to' => $this->comment->commentable->id, // if link to url false, means get link_to_object
            'link_to_object' => $this->comment->commentable_type, // if link to url false, means get link_to_object
            'action' => 'comment_liked',
            'from_name' => $this->user->name,
            'from_id' => $this->user->id,
            'title' => $this->user->name,
            'message' => __('messages.notification.database.CommentLiked'),
            'extra' => [
                'parent_id' => $this->comment->parent_id,
                'reply_to_id' => $this->comment->reply_to_id,
                'comment_id' => $this->comment->id,
            ]
        ];
    }
}
