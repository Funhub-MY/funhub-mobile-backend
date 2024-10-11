<?php

namespace App\Console\Commands;

use App\Models\MerchantOfferClaimRedemptions;
use App\Models\StoreRating;
use App\Notifications\RedeemReview;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendRedeemReviewReminder extends Command
{
    protected $signature = 'redeem:send-review-reminder';
    protected $description = 'Send redeem review reminder to users';

    public function handle()
    {
        // Get recent redemptions (e.g., within the last 7 days)
        $recentRedemptions = MerchantOfferClaimRedemptions::with(['claim', 'claim.merchantOffer', 'claim.merchantOffer.stores', 'user'])
            ->where('created_at', '<=', now()->subHours(2))
            ->whereNull('reminder_sent_at')
            ->get();

        Log::info('[SendRedeemReviewReminder] Total Redemptions before 2 hours: ' . count($recentRedemptions));
        $this->info('[SendRedeemReviewReminder] Total Redemptions before 2 hours: ' . count($recentRedemptions));

        foreach ($recentRedemptions as $redemption) {
            $this->info('Processing redemption for user ID ' . $redemption->user_id. ' and merchant offer ID '. $redemption->claim->merchantOffer->id);
            $user = $redemption->user;
            $stores = $redemption->claim->merchantOffer->stores;

            $hasReviewedAnyStore = false;

            foreach ($stores as $store) {
                // Check if the user has already rated the store
                $existingRating = StoreRating::where('user_id', $user->id)
                    ->where('store_id', $store->id)
                    ->exists();

                if ($existingRating) {
                    $hasReviewedAnyStore = true;
                    break;
                }
            }

            if (!$hasReviewedAnyStore) {
                foreach ($stores as $store) {
                    // Send the RedeemReview notification to the user
                    $user->notify(new RedeemReview($redemption->claim, $user, $store, $redemption->claim->merchant_offer_id));
                    Log::info('[SendRedeemReviewReminder] User Redeemed from Store: ' . $store->id . ' and Notified to remind for review', [
                        'user_id' => $user->id,
                        'store_id' => $store->id,
                    ]);
                }
                $redemption->update(['reminder_sent_at' => now()]);

                $this->info('[SendRedeemReviewReminder] User '. $user->id);
            }
        }
    }
}
