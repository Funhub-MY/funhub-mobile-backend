<?php

namespace App\Console\Commands;

use App\Models\MerchantOfferClaimRedemptions;
use App\Models\StoreRating;
use App\Notifications\RedeemReview;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class SendRedeemReviewReminder extends Command
{
    protected $signature = 'redeem:send-review-reminder';
    protected $description = 'Send redeem review reminder to users';

    public function handle()
    {
        // Get recent redemptions (e.g., more than 2 hours)
		$recentRedemptions = MerchantOfferClaimRedemptions::with(['claim', 'claim.merchantOffer', 'claim.merchantOffer.stores', 'user'])
//			->where('created_at', '>=', now()->subMinutes(30))
			->where('created_at', '<=', now()->subHours(2))
			->whereNull('reminder_sent_at')
			->get();

		Log::info('[SendRedeemReviewReminder] Total Redemptions before 2 hours: ' . count($recentRedemptions));
        $this->info('[SendRedeemReviewReminder] Total Redemptions before 2 hours: ' . count($recentRedemptions));

        foreach ($recentRedemptions as $redemption) {
			Log::info('[SendRedeemReviewReminder] Log into redemptions foreach loop.');
			try {
				if (!$redemption->claim || !$redemption->claim->merchantOffer) {
					throw new \Exception('Claim or Merchant Offer is null for redemption ID ' . $redemption->id);
				}
				Log::info('[SendRedeemReviewReminder] Processing redemption for user ID ' . $redemption->user_id . ' and merchant offer ID ' . $redemption->claim->merchantOffer->id);
				$this->info('Processing redemption for user ID ' . $redemption->user_id . ' and merchant offer ID ' . $redemption->claim->merchantOffer->id);
				$user = $redemption->user;
				$stores = $redemption->claim->merchantOffer->stores;

				$hasReviewedAnyStore = false;

				foreach ($stores as $store) {
					Log::info('[SendRedeemReviewReminder] Store for user', [
						'user_id' => $user->id,
						'store_id' => $store->id,
					]);
					// Check if the user has already rated the store
					$existingRating = StoreRating::where('user_id', $user->id)
						->where('store_id', $store->id)
						->exists();

					if ($existingRating) {
						$hasReviewedAnyStore = true;
						Log::info('[SendRedeemReviewReminder] User has already reviewed store', [
							'user_id' => $user->id,
							'store_id' => $store->id,
						]);
						break;
					}
				}

				if (!$hasReviewedAnyStore) {
					Log::info('[SendRedeemReviewReminder] User has not been reviewed the store');
					foreach ($stores as $store) {
						// Send the RedeemReview notification to the user
						$user->notify(new RedeemReview($redemption->claim, $user, $store, $redemption->claim->merchant_offer_id));
						Log::info('[SendRedeemReviewReminder] User Redeemed from Store: ' . $store->id . ' and Notified to remind for review', [
							'user_id' => $user->id,
							'store_id' => $store->id,
						]);
					}
					$redemption->update(['reminder_sent_at' => now()]);

					$this->info('[SendRedeemReviewReminder] User ' . $user->id);
				}
			} catch (\Exception $e) {
				Log::error('[SendRedeemReviewReminder] Error processing redemption ID: ' . $redemption->id, [
					'error' => $e->getMessage(),
					'stack' => $e->getTraceAsString(),
				]);
				$this->error('Error processing redemption ID: ' . $redemption->id . ' - ' . $e->getMessage());
			}
        }
		Log::info('[SendRedeemReviewReminder] redemption foreach loop ended');
    }
}
