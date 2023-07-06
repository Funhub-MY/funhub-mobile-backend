<?php

namespace App\Notifications;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use Illuminate\Support\Str;

class CommentLiked extends Notification implements ShouldQueue
{
    use Queueable;

    protected $comment;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Comment $comment)
    {
        $this->comment = $comment;
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
                'commentor_id' => (string) $this->comment->user->id,
                'article_id' => (string) $this->comment->commentable->id,
                'article_type' => (string) $this->comment->commentable->type,
                'action' => 'comment_liked'
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle('New Comment Like')
                ->setBody( $this->comment->user->name . '赞了你的评论 "' . Str::limit($this->comment->body, 10, '...') . '"')
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
            'action' => 'commented',
            'from' => $this->comment->user->name,
            'from_id' => $this->comment->user->id,
            'message' => $this->comment->user->name . '赞了你的评论 "' . Str::limit($this->comment->body, 10, '...') . '"',
        ];
    }
}
