<?php

namespace App\Listeners;

use App\Events\PurchasedMerchantOffer;
use App\Models\MerchantOfferVoucher;
use App\Services\MixpanelService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class TrackPurchasedMerchantOfferListener implements ShouldQueue
{
    /**
     * The MixpanelService instance.
     *
     * @var \App\Services\MixpanelService
     */
    protected $mixpanelService;

    /**
     * Create the event listener.
     *
     * @param \App\Services\MixpanelService $mixpanelService
     * @return void
     */
    public function __construct(MixpanelService $mixpanelService)
    {
        $this->mixpanelService = $mixpanelService;
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\PurchasedMerchantOffer  $event
     * @return void
     */
    public function handle(PurchasedMerchantOffer $event)
    {
        try {
            // Find the voucher associated with this purchase
            $voucher = MerchantOfferVoucher::where('merchant_offer_id', $event->merchantOffer->id)
                ->where('owned_by_id', $event->user->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$voucher) {
                Log::warning('Cannot track purchase: no voucher found', [
                    'user_id' => $event->user->id,
                    'merchant_offer_id' => $event->merchantOffer->id,
                    'payment_method' => $event->paymentMethod
                ]);
                return;
            }

            // Track the voucher sale using the MixpanelService
            $this->mixpanelService->trackVoucherSale($voucher);
            
            Log::info('Successfully tracked merchant offer purchase in Mixpanel', [
                'voucher_id' => $voucher->id,
                'merchant_offer_id' => $event->merchantOffer->id,
                'user_id' => $event->user->id
            ]);
        } catch (\Exception $e) {
            Log::error('Error tracking merchant offer purchase: ' . $e->getMessage(), [
                'exception' => $e,
                'merchant_offer_id' => $event->merchantOffer->id ?? null,
                'user_id' => $event->user->id ?? null
            ]);
        }
    }
}
