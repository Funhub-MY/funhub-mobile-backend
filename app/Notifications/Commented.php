<?php

namespace App\Notifications;

use App\Models\Article;
use App\Models\Comment;
use Illuminate\Bus\Queueable;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\Fcm\Resources\ApnsConfig;
use NotificationChannels\Fcm\Resources\AndroidConfig;
use NotificationChannels\Fcm\Resources\ApnsFcmOptions;
use NotificationChannels\Fcm\Resources\AndroidFcmOptions;
use NotificationChannels\Fcm\Resources\AndroidNotification;

class Commented extends Notification implements ShouldQueue
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
                'object' => (string) get_class($this->comment),
                'object_id' => (string) $this->comment->id,
                'article_id' => ($this->comment->commentable_type == Article::class) ? (string) $this->comment->commentable->id : null,
                'article_type' => ($this->comment->commentable_type == Article::class) ? (string) $this->comment->commentable->type : null,
                'link_to_url' => (string) 'false',
                'link_to' => (string) $this->comment->commentable->id, // if link to url false, means get link_to_object
                'link_to_object' => (string) $this->comment->commentable_type, // if link to url false, means get link_to_object
                'action' => (string) 'commented',
                'from_name' => (string) $this->comment->user->name,
                'from_id' => (string) $this->comment->user->id,
                'title' => (string) $this->comment->user->name,
                'message' => __('messages.notification.database.Commented'),
                'extra' => json_encode([
                    'parent_id' => ($this->comment->parent_id) ? (string) $this->comment->parent_id : null,
                    'comment_id' => (string) $this->comment->id,
                ])
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle(__('messages.notification.fcm.CommentedTitle'))
                ->setBody(__('messages.notification.fcm.Commented', [
                    'username' => $this->comment->user->name,
                    'commentTitle' => $this->comment->commentable->title
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
            'action' => 'commented',
            'from_name' => $this->comment->user->name,
            'from_id' => $this->comment->user->id,
            'title' => $this->comment->user->name,
            'message' => __('messages.notification.database.Commented'),
            'extra' => [
                'parent_id' => ($this->comment->parent_id) ? $this->comment->parent_id : null,
                'comment_id' => $this->comment->id,
            ]
        ];
    }
}
