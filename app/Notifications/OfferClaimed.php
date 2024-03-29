<?php

namespace App\Notifications;

use App\Models\MerchantOffer;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;

class OfferClaimed extends Notification
{
    use Queueable;

    protected $offer, $user, $purchaseMethod, $price;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(MerchantOffer $offer, User $user, $purchaseMethod, $price)
    {
        $this->offer = $offer;
        $this->user = $user;
        $this->purchaseMethod = $purchaseMethod;
        $this->price = $price;
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
        if ($this->purchaseMethod == 'points') {
            return __('messages.notification.fcm.OfferClaimed.points', [
                'price' => $this->price,
                'offerName' => $this->offer->name
            ]);
        } else if ($this->purchaseMethod == 'fiat') {
            return __('messages.notification.fcm.OfferClaimed.fiat', [
                'price' => $this->price,
                'offerName' => $this->offer->name
            ]);
        } else {}
    }

    public function toFcm($notifiable)
    {
        return FcmMessage::create()
            ->setData([
                'offer_id' => (string) $this->offer->id,
                'claim_user_id' => (string) $this->user->id,
                'action' => 'claimed_offer'
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle('交易成功')
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
            'action' => 'claimed_offer',
            'from' => $this->user->name,
            'from_id' => $this->user->id,
            'title' => '交易成功',
            'message' => $this->getMessage(),
        ];
    }
}
