<?php

namespace App\Notifications;

use App\Models\MerchantOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\ApnsConfig;
use NotificationChannels\Fcm\Resources\AndroidConfig;
use NotificationChannels\Fcm\Resources\ApnsFcmOptions;
use NotificationChannels\Fcm\Resources\AndroidFcmOptions;
use NotificationChannels\Fcm\Resources\AndroidNotification;

class NewMerchantOfferListed extends Notification implements ShouldQueue
{
    use Queueable;

    protected $merchantOffer;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(MerchantOffer $merchantOffer)
    {
        $this->merchantOffer = $merchantOffer;
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
                'object' => (string) get_class($this->merchantOffer),
                'object_id' => (string) $this->merchantOffer->id,
                'link_to_url' => (string) 'false',
                'link_to' => (string) $this->merchantOffer->id, // if link to url false, means get link_to_object
                'link_to_object' => (string) $this->merchantOffer->id, // if link to url false, means get link_to_object
                'action' => (string) 'commented',
                'from_name' => (string) 'Funhub',
                'from_id' => '',
                'title' => (string) _('messages.notification.fcm.NewOfferListed'),
                'message' => __('messages.notification.database.Commented'),
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle(__('messages.notification.fcm.NewOfferListed'))
                ->setBody(__('messages.notification.fcm.NewOfferListedBody', [
                    'brandName' => $this->merchantOffer->merchant->brand_name ?? ''
                ]))
            )
            ->setApns(
                ApnsConfig::create()
                    ->setFcmOptions(ApnsFcmOptions::create()->setAnalyticsLabel('analytics_ios'))
                    ->setPayload(['aps' => ['sound' => 'default']])
            )
            ->setAndriod(
                AndroidConfig::create()
                    ->setFcmOptions(AndroidFcmOptions::create()->setAnalyticsLabel('analytics_android'))
                    ->setNotification(AndroidNotification::create()->setSound('default'))
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
            'object' => get_class($this->merchantOffer),
            'object_id' => $this->merchantOffer->id,
            'link_to_url' => false,
            'link_to' => $this->merchantOffer->id, // if link to url false, means get link_to_object
            'link_to_object' => $this->merchantOffer->id, // if link to url false, means get link_to_object
            'action' => 'new_offer',
            'from_name' => 'Funhub',
            'from_id' => '',
            'title' => _('messages.notification.database.NewOfferListed'),
            'message' => __('messages.notification.database.NewOfferListedBody', [
                'brandName' => $this->merchantOffer->merchant->brand_name ?? ''
            ]),
        ];
    }
}
