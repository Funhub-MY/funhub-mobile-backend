<?php

namespace App\Notifications;

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

        // Determine the locale based on the user's last_lang or use the system default
        $locale = $comment->user->last_lang ?? config('app.locale');

        // Set the locale for this notification
        app()->setLocale($locale);
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
                'comment_id' => (string) $this->comment->id,
                'liker_id' => (string) $this->user->id,
                'article_id' => (string) $this->comment->commentable->id,
                'article_type' => (string) $this->comment->commentable->type,
                'action' => 'comment_liked'
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
            'from' => $this->user->name,
            'from_id' => $this->user->id,
            'title' => $this->user->name,
            'message' => __('messages.notification.database.CommentLiked'),
        ];
    }
}
