<?php

namespace App\Listeners;

use Exception;
use App\Events\GiftCardPurchased;
use App\Notifications\ProductPurchaseNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendProductPurchaseNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;
    
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        // Set the queue for this listener
        $this->queue = 'notifications';
    }

    /**
     * Handle the event.
     *
     * @param GiftCardPurchased $event
     * @return void
     */
    public function handle(GiftCardPurchased $event)
    {
        $product = $event->product;
        $user = $event->user;
        
        // Check if the product has purchase notifications enabled
        if ($product->enable_purchase_notification) {
            try {
                // Get user's preferred locale
                $userLocale = $user->last_lang ?? config('app.locale');
                
                // Check if the notification messages are set
                if (empty($product->purchase_notification_en) && empty($product->purchase_notification_zh)) {
                    Log::warning('Product purchase notification is enabled but no notification messages are set', [
                        'product_id' => $product->id,
                        'product_name' => $product->name
                    ]);
                    return;
                }
                
                // Send the notification to the user
                $user->notify(new ProductPurchaseNotification($product, $user));
                
                Log::info('Product purchase notification sent', [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'user_id' => $user->id,
                    'locale' => $user->last_lang ?? config('app.locale')
                ]);
            } catch (Exception $e) {
                Log::error('Failed to send product purchase notification', [
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
