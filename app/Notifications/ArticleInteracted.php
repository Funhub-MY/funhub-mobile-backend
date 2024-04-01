<?php

namespace App\Notifications;

use App\Models\Interaction;
use Illuminate\Bus\Queueable;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ArticleInteracted extends Notification implements ShouldQueue
{
    use Queueable;

    protected $interaction;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Interaction $interaction)
    {
        $this->interaction = $interaction;

        // Determine the locale based on the user's last_lang or use the system default
        $locale = $interaction->interactable->user->last_lang ?? config('app.locale');

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
                'interaction_id' => (string) $this->interaction->id,
                'interaction_user' => (string) $this->interaction->user->id,
                'article_id' => (string) $this->interaction->interactable->id,
                'article_type' => (string) $this->interaction->interactable->type,
                'action' => 'article_interacted'
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle('探文互动')
                ->setBody(__('messages.notification.fcm.ArticleInteracted', [
                    'username' => $this->interaction->user->name,
                    'action' => $this->getAction(),
                    'articleTitle' => $this->interaction->interactable->title
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
            'object' => get_class($this->interaction),
            'object_id' => $this->interaction->id,
            'link_to_url' => false,
            'link_to' => $this->interaction->interactable->id, // if link to url false, means get link_to_object
            'link_to_object' => $this->interaction->interactable_type, // if link to url false, means get link_to_object
            'action' => 'article_interacted',
            'from' => $this->interaction->user->name,
            'from_id' => $this->interaction->user->id,
            'title' => $this->interaction->user->name,
            'message' => __('messages.notification.database.ArticleInteracted'),
        ];
    }

    private function getAction()
    {
        switch($this->interaction->type) {
            case Interaction::TYPE_LIKE:
                return '赞了';
            // case Interaction::TYPE_DISLIKE:
            //     return 'disliked';
            // case Interaction::TYPE_SHARE:
            //     return 'shared';
            // case Interaction::TYPE_BOOKMARK:
            //     return 'bookmarked';
            default:
                return '';
        }
    }
}
