<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProductPurchaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $product;
    protected $userLocale;
    protected $notificationMessage;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Product $product, $userLocale = null)
    {
        $this->product = $product;
        $this->userLocale = $userLocale ?? config('app.locale');
        
        // use the notifications queue with specific settings
        $this->onQueue('notifications');
        
        // Determine which notification message to use based on locale
        if ($this->userLocale === 'zh') {
            $this->notificationMessage = $product->purchase_notification_zh;
        } else {
            $this->notificationMessage = $product->purchase_notification_en;
        }
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

    /**
     * Get the FCM representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return FcmMessage
     */
    public function toFcm($notifiable)
    {
        $data = [
            'title' => $this->product->name,
            'message' => $this->notificationMessage,
            'object' => get_class($this->product), // App\Models\Product
            'object_id' => (string) $this->product->id,
            'link_to_url' => !empty($this->product->purchase_notification_url) ? 'true' : 'false',
            'link_to' => !empty($this->product->purchase_notification_url) ? $this->product->purchase_notification_url : 'null',
            'link_to_object' => (string) $this->product->id,
            'action' => 'product_purchase_notification',
            'from_name' => 'Funhub',
            'from_id' => '',
        ];

        return FcmMessage::create()
            ->setData($data)
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle($this->product->name)
                ->setBody($this->notificationMessage)
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
            'title' => $this->product->name,
            'message' => $this->notificationMessage,
            'object' => get_class($this->product), // App\Models\Product
            'object_id' => (string) $this->product->id,
            'link_to_url' => !empty($this->product->purchase_notification_url),
            'link_to' => !empty($this->product->purchase_notification_url) ? $this->product->purchase_notification_url : null,
            'link_to_object' => $this->product->id,
            'action' => 'product_purchase_notification',
            'from_name' => 'Funhub',
            'from_id' => '',
        ];
    }
}
