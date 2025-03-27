<?php

namespace App\Services;

use GeneaLabs\LaravelMixpanel\Facades\Mixpanel;
use Illuminate\Support\Facades\Log;

class MixpanelService
{
    /**
     * track voucher sale data in Mixpanel
     *
     * @param \App\Models\MerchantOfferVoucher $voucher the voucher to track
     * @param bool $dryRun whether to run in dry-run mode (no actual tracking)
     * @return bool success or failure
     */
    public function trackVoucherSale($voucher, bool $dryRun = false): bool
    {
        try {
            // load all necessary relationships if not already loaded
            if (!$voucher->relationLoaded('merchant_offer') || 
                !$voucher->relationLoaded('merchant_offer.user') || 
                !$voucher->relationLoaded('merchant_offer.user.merchant')) {
                $voucher->load(['merchant_offer', 'merchant_offer.user', 'merchant_offer.user.merchant']);
            }
            
            if (!$voucher->relationLoaded('claim') || 
                ($voucher->claim && !$voucher->claim->relationLoaded('user')) ||
                ($voucher->claim && !$voucher->claim->relationLoaded('transaction'))) {
                $voucher->load(['claim', 'claim.user', 'claim.transaction']);
            }
            
            $merchantOffer = $voucher->merchant_offer;
            
            if (!$merchantOffer) {
                Log::warning('cannot track voucher sale: merchant offer not found', ['voucher_id' => $voucher->id]);
                return false;
            }
            
            // get merchant/brand name
            $brandName = 'Unknown';
            if ($merchantOffer->user && $merchantOffer->user->merchant) {
                $brandName = $merchantOffer->user->merchant->brand_name ?? 'Unknown';
            }
            
            // get payment method from transaction data
            $paymentMethod = 'Unknown';
            
            // try to get the transaction associated with this voucher
            if ($voucher->claim) {
                // load transaction if not already loaded
                if (!$voucher->claim->relationLoaded('transaction')) {
                    $voucher->claim->load('transaction');
                }
                
                // check if transaction exists and has payment_method
                if ($voucher->claim->transaction && !empty($voucher->claim->transaction->payment_method)) {
                    $paymentMethod = $voucher->claim->transaction->payment_method;
                } else {
                    // if no transaction or payment_method, determine based on merchant offer type
                    if ($merchantOffer->is_points_offer) {
                        $paymentMethod = 'Funbox Points';
                    } else {
                        $paymentMethod = 'Cash';
                    }
                }
            }
            
            // get amount - use point_fiat_price or fallback to unit_price
            $amount = $merchantOffer->point_fiat_price ?? $merchantOffer->unit_price ?? 0;
            
            // get purchase datetime
            $purchaseDateTime = null;
            if ($voucher->claim) {
                $purchaseDateTime = $voucher->claim->created_at;
            } else {
                $purchaseDateTime = $voucher->created_at;
            }
            
            // format the purchase date time as required
            $formattedDateTime = $purchaseDateTime->timezone('UTC')->format('d/m/Y H:i:s');
            
            // get user information safely
            $userEmail = null;
            $userName = null;
            if ($voucher->claim && $voucher->claim->user) {
                $userEmail = $voucher->claim->user->email;
                $userName = $voucher->claim->user->name;
            }
            
            // create event properties
            $properties = [
                'item_name' => $merchantOffer->name ?? 'Unknown',
                'offer_sku' => $merchantOffer->sku,
                'brand_name' => $brandName,
                'user_id' => $voucher->owned_by_id,
                'user_email' => $userEmail,
                'user_name' => $userName,
                'payment_method' => $paymentMethod,
                'amount' => (float) $amount,
                'purchase_date_time' => $formattedDateTime,
                'voucher_id' => $voucher->id,
                'sku' => $voucher->code,
                '$insert_id' => (string) $voucher->id, // ensure it's a string
                'merchant_offer_id' => $merchantOffer->id,
                'time' => $purchaseDateTime->timestamp * 1000,
                'timestamp' => $purchaseDateTime->timestamp * 1000 // convert to milliseconds
            ];
            
            // use user ID as the distinct ID for proper user tracking
            $distinctId = (string) 'funhub-mobile-users-' . $voucher->owned_by_id; // ensure it's a string

            if ($dryRun) {
                Log::info('dry run - would track the following properties:', $properties);
                return true;
            }
            $mixpanel = Mixpanel::getFacadeRoot();
            // Create a user profile first to ensure our distinct ID is used as canonical
            $mixpanel->identify($distinctId);
            $envPrefix = app()->environment('production') ? 'prod' : 'dev';
            $eventName = "{$envPrefix}_voucher_sale_data";
            // Now track the event
            $mixpanel->track($eventName, $properties, $distinctId);
            
            return true;
        } catch (\Exception $e) {
            Log::error('error tracking voucher sale: ' . $e->getMessage(), [
                'voucher_id' => $voucher->id ?? null,
                'exception' => $e
            ]);
            return false;
        }
    }
}
