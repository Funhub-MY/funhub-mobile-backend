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

class CommentReplied extends Notification
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
                'object' => (string) get_class($this->comment), // comment object
                'object_id' => (string) $this->comment->parent->id, // returns parent comment
                'link_to_url' => (string) 'false',
                'link_to' => (string) $this->comment->commentable->id, // if link to url false, means get link_to_object
                'link_to_object' => (string) $this->comment->commentable_type, // if link to url false, means get link_to_object
                'action' => 'comment_replied',
                'from_name' => (string) $this->comment->user->name,
                'from_id' => (string) $this->comment->user->id,
                'title' => (string) $this->comment->user->name,
                'message' => __('messages.notification.database.CommentReplied'),
                'comment_id' => (string) $this->comment->id,
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle('探文互动')
                ->setBody(__('messages.notification.fcm.CommentReplied', [
                    'username' => $this->comment->user->name,
                    'comment' => Str::limit($this->comment->parent->body, 10, '...')
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
            'object_id' => $this->comment->parent->id, // returns parent comment
            'link_to_url' => false,
            'link_to' => $this->comment->commentable->id, // if link to url false, means get link_to_object
            'link_to_object' => $this->comment->commentable_type, // if link to url false, means get link_to_object
            'action' => 'comment_replied',
            'from_name' => $this->comment->user->name,
            'from_id' => $this->comment->user->id,
            'title' => $this->comment->user->name,
            'message' => __('messages.notification.database.CommentReplied'),
            'comment_id' => (string) $this->comment->id,
        ];
    }
}
