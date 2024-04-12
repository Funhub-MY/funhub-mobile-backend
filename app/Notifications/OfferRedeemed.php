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
        return __('messages.notification.fcm.OfferRedeemed', ['offerName' => $this->user->name]);
    }

    public function toFcm($notifiable)
    {
        return FcmMessage::create()
            ->setData([
                'offer_id' => (string) $this->offer->id,
                'claim_user_id' => (string) $this->user->id,
                'action' => 'offer_redeemed'
            ])
            ->setNotification(
                \NotificationChannels\Fcm\Resources\Notification::create()
                    ->setTitle('兑换成功')
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
            'from' => $this->user->name,
            'from_id' => $this->user->id,
            'title' => '兑换成功',
            'message' => $this->getMessage(),
        ];
    }
}
