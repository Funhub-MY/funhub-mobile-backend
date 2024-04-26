<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Approval;
use Illuminate\Bus\Queueable;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NewFunboxRewardApproved extends Notification implements ShouldQueue
{
    use Queueable;

    protected $approval, $user;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Approval $approval, User $user)
    {
        $this->approval = $approval;
        $this->user = $user;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return arrayO
     */
    public function via($notifiable)
    {
        return [FcmChannel::class, 'database'];
    }

    protected function getMessage()
    {
        return __('messages.notification.fcm.NewFunboxRewardApproved', ['username' => $this->user->name]);
    }

    public function toFcm($notifiable)
    {
        return FcmMessage::create()
            ->setData([
                'object' => (string) get_class($this->approval),
                'object_id' => (string) $this->approval->id,
                'link_to_url' => (string) 'false',
                'link_to' => (string) $this->approval->id, // if link to url false, means get link_to_object
                'link_to_object' => (string) 'null', // if link to url false, means get link_to_object
                'action' => 'new_funbox_reward_approved',
                'from_name' => (string) $this->user->name,
                'from_id' => (string) $this->user->id,
                'title' => '新饭盒',
                'message' => (string) $this->getMessage(),
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                    ->setTitle('新饭盒')
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
            'object' => get_class($this->approval),
            'object_id' => $this->approval->id,
            'link_to_url' => false,
            'link_to' => $this->approval->id, // if link to url false, means get link_to_object
            'link_to_object' => null, // if link to url false, means get link_to_object
            'action' => 'new_funbox_reward_approved',
            'from_name' => $this->user->name,
            'from_id' => $this->user->id,
            'title' => '新饭盒',
            'message' => $this->getMessage(),
        ];
    }
}
