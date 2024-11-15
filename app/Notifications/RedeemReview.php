<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferClaim;
use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class RedeemReview extends Notification
{
    use Queueable;

    protected $claim, $user, $store, $merchant_offer_id, $claim_id, $merchant_id;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(MerchantOfferClaim  $claim, User $user, Store $store = null, $merchant_offer_id = null)
    {
        $this->claim = $claim;
        $this->user = $user;
        $this->store = $store;
        $this->merchant_offer_id = $merchant_offer_id;

        $this->claim_id = null;
        $this->merchant_id = null;
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

    protected function getOffer()
    {
        if ($this->merchant_offer_id) {
            $offer = MerchantOffer::find($this->merchant_offer_id);
            if ($offer) {
                $this->merchant_id = ($offer->user) ? $offer->user->merchant->id : null;
            }
        }
    }

    protected function getClaim()
    {
        if ($this->merchant_offer_id && $this->user) {
            // get claim_id of offer
            $claim = MerchantOfferClaim::where('merchant_offer_id', $this->merchant_offer_id)
                ->where('user_id', $this->user->id)
                ->latest()
                ->first();
            if ($claim) {
                $this->claim_id = $claim->id;
            }
        }
    }

    public function toFcm($notifiable)
    {
        $this->getClaim();
        $this->getOffer();

        return FcmMessage::create()
            ->setData([
                'object' => $this->store ? (string) get_class($this->store) : 'App\Models\Store',
                'object_id' => $this->store ? (string) $this->store->id : '',
                'merchant_offer_id' => (string) $this->merchant_offer_id,
                'link_to_url' => (string) 'false',
                'link_to' => ($this->store) ? (string) $this->store->id : '', // if link to url false, means get link_to_object
                'link_to_object' => (string) 'null', // if link to url false, means get link_to_object
                'action' => 'redeemed_review',
                'from_name' => (string) $this->user->name,
                'from_id' => (string) $this->user->id,
                'title' => __('messages.notification.fcm.RedemptioReviewReminderTitle'),
                'message' => (string) $this->getMessage(),
                'extra' => json_encode([
                    'offer_id' => (string) $this->merchant_offer_id,
                    'merchant_id' => (string) $this->merchant_id,
                    'claim_id' => (string) $this->claim_id
                ])
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
        $this->getClaim();
        $this->getOffer();

		Log::info("Redeem review notification completed");
        return [
            'object' => (string) get_class($this->store),
            'object_id' =>($this->store) ?  (string) $this->store->id : null,
            'merchant_offer_id' => (string) $this->merchant_offer_id,
            'link_to_url' => false,
            'link_to' => (string) ($this->store) ? $this->store->id : null, // if link to url false, means get link_to_object
            'link_to_object' => null, // if link to url false, means get link_to_object
            'action' => 'redeemed_review',
            'from_name' => $this->user->name,
            'from_id' => $this->user->id,
            'title' => __('messages.notification.database.RedemptioReviewReminderTitle'),
            'message' => $this->getMessage(),
            'extra' => json_encode([
                'offer_id' => (string) $this->merchant_offer_id,
                'merchant_id' => (string) $this->merchant_id,
                'claim_id' => (string) $this->claim_id
            ])
        ];
    }
}
