<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferClaim;
use App\Models\Store;
use Illuminate\Bus\Queueable;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class RedeemReview extends Notification
{
    use Queueable;

    protected $claim, $user, $store;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(MerchantOfferClaim  $claim, User $user, Store $store = null)
    {
        $this->claim = $claim;
        $this->user = $user;
        $this->store = $store;
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

    protected function getMessage()
    {
        return __('messages.notification.fcm.RedemptioReviewReminder', [
            'storeName' => ($this->store) ? $this->store->name : ''
        ]);
    }

    public function toFcm($notifiable)
    {

        return FcmMessage::create()
            ->setData([
                'object' => (string) get_class($this->store),
                'object_id' => (string) ($this->store) ? $this->store->id : null,
                'link_to_url' => (string) 'false',
                'link_to' => (string) ($this->store) ? $this->store->id : null, // if link to url false, means get link_to_object
                'link_to_object' => (string) 'null', // if link to url false, means get link_to_object
                'action' => 'redeemed_review',
                'from_name' => (string) $this->user->name,
                'from_id' => (string) $this->user->id,
                'title' => __('messages.notification.fcm.RedemptioReviewReminderTitle'),
                'message' => (string) $this->getMessage(),
            ])
            ->setNotification(
                \NotificationChannels\Fcm\Resources\Notification::create()
                    ->setTitle( __('messages.notification.fcm.RedemptioReviewReminderTitle'))
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
            'object' => (string) get_class($this->store),
            'object_id' => (string) ($this->store) ? $this->store->id : null,
            'link_to_url' => false,
            'link_to' => (string) ($this->store) ? $this->store->id : null, // if link to url false, means get link_to_object
            'link_to_object' => null, // if link to url false, means get link_to_object
            'action' => 'redeemed_review',
            'from_name' => $this->user->name,
            'from_id' => $this->user->id,
            'title' => __('messages.notification.database.RedemptioReviewReminderTitle'),
            'message' => $this->getMessage(),
        ];
    }
}
