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
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        Log::info('[SendRedeemReviewReminder] Total Redemptions Past 7 days: ' . count($recentRedemptions));
        $this->info('[SendRedeemReviewReminder] Total Redemptions Past 7 days: ' . count($recentRedemptions));

        foreach ($recentRedemptions as $redemption) {
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
                // Send the RedeemReview notification to the user
                $user->notify(new RedeemReview($redemption->claim, $user, $store));
                Log::info('[SendRedeemReviewReminder] User Redeemed from Store: ' . $store->id . ' and Notified to remind for review', [
                    'user_id' => $user->id,
                    'store_id' => $store->id,
                ]);

                $this->info('[SendRedeemReviewReminder] User '. $user->id .' Redeemed from Store: ' . $store->id . ' and Notified to remind for review');
            }
        }
    }
}
