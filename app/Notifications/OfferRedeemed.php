<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\MerchantOffer;
use Illuminate\Bus\Queueable;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\MerchantOfferClaimRedemptions;
use Illuminate\Notifications\Messages\MailMessage;

class OfferRedeemed extends Notification
{
    use Queueable;

    protected $offer, $user;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(MerchantOffer $offer, User $user)
    {
        $this->offer = $offer;
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
        return [FcmChannel::class, 'database'];
    }

    protected function getMessage()
    {
        return __('messages.notification.fcm.OfferRedeemed', ['offerName' => $this->offer->name]);
    }

    public function toFcm($notifiable)
    {
        return FcmMessage::create()
            ->setData([
                'object' => (string) get_class($this->offer),
                'object_id' => (string) $this->offer->id,
                'link_to_url' => (string) 'false',
                'link_to' => (string) $this->offer->id, // if link to url false, means get link_to_object
                'link_to_object' => (string) 'null', // if link to url false, means get link_to_object
                'action' => 'offer_redeemed',
                'from_name' => (string) $this->user->name,
                'from_id' => (string) $this->user->id,
                'title' => __('messages.notification.fcm.RedemptionSuccessful'),
                'message' => (string) $this->getMessage(),
            ])
            ->setNotification(
                \NotificationChannels\Fcm\Resources\Notification::create()
                    ->setTitle(__('messages.notification.fcm.RedemptionSuccessful'))
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
            'object' => get_class($this->offer),
            'object_id' => $this->offer->id,
            'link_to_url' => false,
            'link_to' => $this->offer->id, // if link to url false, means get link_to_object
            'link_to_object' => null, // if link to url false, means get link_to_object
            'action' => 'offer_redeemed',
            'from_name' => $this->user->name,
            'from_id' => $this->user->id,
            'title' =>  __('messages.notification.fcm.RedemptionSuccessful'),
            'message' => $this->getMessage(),
        ];
    }
}
