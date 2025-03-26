<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class CompletedNewbieMission extends Notification implements ShouldQueue
{
    use Queueable;

    protected $user;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(User $user)
    {
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
        $channels = ['database'];
        $channels[] = FcmChannel::class;
        
        return $channels;
    }

    protected function getMessage()
    {
        return __('messages.notification.fcm.NewbieMissionsCompletedMessage', [], 
            $this->user->last_lang ?? config('app.locale'));
    }

    public function toFcm($notifiable)
    {
        $title = __('messages.notification.fcm.NewbieMissionsCompletedTitle', [], 
            $this->user->last_lang ?? config('app.locale'));
            
        return FcmMessage::create()
            ->setData([
                'object' => (string) get_class($this->user),
                'object_id' => (string) $this->user->id,
                'link_to_url' => (string) 'false',
                'link_to' => (string) 'missions', 
                'link_to_object' => (string) 'null',
                'action' => 'newbie_missions_completed',
                'from_name' => (string) $this->user->name,
                'from_id' => (string) $this->user->id,
                'title' => (string) $title,
                'message' => (string) $this->getMessage(),
                'extra' => json_encode([])
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle($title)
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
        $title = __('messages.notification.fcm.NewbieMissionsCompletedTitle', [], 
            $this->user->last_lang ?? config('app.locale'));

        return [
            'object' => get_class($this->user),
            'object_id' => $this->user->id,
            'link_to_url' => false,
            'link_to' => 'missions',
            'link_to_object' => null,
            'action' => 'newbie_missions_completed',
            'from_name' => $this->user->name,
            'from_id' => $this->user->id,
            'title' => $title,
            'message' => $this->getMessage(),
            'extra' => []
        ];
    }
}
