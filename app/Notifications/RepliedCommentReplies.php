<?php

namespace App\Notifications;

use App\Models\Comment;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class RepliedCommentReplies extends Notification
{
    use Queueable;

    protected $comment, $replyingComment;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Comment $comment, Comment $replyingComment)
    {
        $this->comment = $comment;
        $this->replyingComment = $replyingComment;
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
                'object' => (string) get_class($this->comment), // comment object
                'object_id' => (string) $this->replyingComment->id, // returns parent comment
                'link_to_url' => (string) 'false',
                'link_to' => (string) $this->comment->commentable->id, // if link to url false, means get link_to_object
                'link_to_object' => (string) $this->comment->commentable_type, // if link to url false, means get link_to_object
                'action' => 'replied_replies',
                'from_name' => (string) $this->comment->user->name,
                'from_id' => (string) $this->comment->user->id,
                'title' => (string) $this->comment->user->name,
                'message' => __('messages.notification.database.CommentReplied', ['username' => $this->comment->user->name , 'comment' => Str::limit($this->replyingComment->body, 10, '...')]),
                'comment_id' => (string) $this->comment->id,
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle(__('messages.notification.fcm.CommentRepliedTitle'))
                ->setBody(__('messages.notification.fcm.CommentReplied', [
                    'username' => $this->comment->user->name,
                    'comment' => Str::limit($this->replyingComment->body, 10, '...')
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
            'object' => get_class($this->comment), // comment object
            'object_id' => $this->replyingComment->id, // returns replyingComment
            'link_to_url' => false,
            'link_to' => $this->comment->commentable->id, // if link to url false, means get link_to_object
            'link_to_object' => $this->comment->commentable_type, // if link to url false, means get link_to_object
            'action' => 'replied_replies',
            'from_name' => $this->comment->user->name,
            'from_id' => $this->comment->user->id,
            'title' => $this->comment->user->name,
            'message' => __('messages.notification.database.CommentReplied', ['username' => $this->comment->user->name , 'comment' => Str::limit($this->replyingComment->body, 10, '...')]),
            'comment_id' => (string) $this->comment->id,
            'extra' => [
                'parent_id' => $this->replyingComment->parent_id,
                'reply_to_id' => $this->comment->reply_to_id,
                'comment_id' => $this->comment->id,
            ]
        ];
    }
}
