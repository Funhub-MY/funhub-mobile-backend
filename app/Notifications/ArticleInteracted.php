<?php

namespace App\Notifications;

use App\Models\Interaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;

class ArticleInteracted extends Notification
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
            ->setData(['interaction_id' => (string) $this->interaction->id, 'interaction_user' => (string) $this->interaction->user->id])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle( 'New '.$this->getAction())
                ->setBody($this->interaction->user->name .' '. $this->getAction().'你的"' . $this->interaction->interactable->title.'"')
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
            'action' => $this->getAction(),
            'from' => $this->interaction->user->name,
            'from_id' => $this->interaction->user->id,
            'message' => $this->interaction->user->name .' '. $this->getAction().'你的"' . $this->interaction->interactable->title.'"',
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
