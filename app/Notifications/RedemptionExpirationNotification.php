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

class RedemptionExpirationNotification extends Notification
{
    use Queueable;

    protected $offer, $user, $daysLeft;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(MerchantOffer $offer, User $user, $daysLeft)
    {
        $this->offer = $offer;
        $this->user = $user;
        $this->daysLeft = $daysLeft;
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
        $expirationText = '';
        return $expirationText.'优惠券"'.$this->offer->name.'"即将于'.$this->daysLeft.'天后逾期';
    }

    public function toFcm($notifiable)
    {
        return FcmMessage::create()
            ->setData([
                'offer_id' => (string) $this->offer->id,
                'claim_user_id' => (string) $this->user->id,
                'action' => 'offer_redeem_expiration'
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle('优惠券即将逾期')
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
            'action' => 'offer_redeem_expiration',
            'from' => $this->user->name,
            'from_id' => $this->user->id,
            'title' => '优惠券即将逾期',
            'message' => $this->getMessage(),
        ];
    }
}
