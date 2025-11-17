<?php

namespace App\Console\Commands;

use App\Notifications\RedemptionExpirationNotification;
use Exception;
use App\Models\MerchantOfferClaim;
use Illuminate\Console\Command;

use Illuminate\Support\Facades\Log;

class NotifyMerchantOffersRedeemExpiration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'merchant-offers:send-expiring-notification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notification to user when merchant offer redemption is expiring in 3 days and 1 day';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            //get the users' merchant offer claims that doesn't have any redemption
            $merchantOffersWithoutRedeem = MerchantOfferClaim::whereDoesntHave('redeem')
            ->where('status', MerchantOfferClaim::CLAIM_SUCCESS)
            ->get();

            if ($merchantOffersWithoutRedeem) {
                foreach ($merchantOffersWithoutRedeem as $merchantOfferWithoutRedeem) {

                    $expiryDays = $merchantOfferWithoutRedeem->merchantOffer->expiry_days;
                    //check if expiry days is set
                    if ($expiryDays !== null) {
                        $createdAt = $merchantOfferWithoutRedeem->created_at->startOfDay();
                        $expirationDate = $createdAt->addDays($expiryDays)->endOfDay();
                        $daysLeft = now()->diffInDays($expirationDate);
    
                        if ($daysLeft === 3 || $daysLeft === 7) {
                            $locale = $merchantOfferWithoutRedeem->user->last_lang ?? config('app.locale');
                            $merchantOfferWithoutRedeem->user->notify((new RedemptionExpirationNotification($merchantOfferWithoutRedeem->merchantOffer, $merchantOfferWithoutRedeem->user, $daysLeft))->locale($locale));
                        // Log the information for each record
                        Log::info('[NotifyMerchantOffersRedeemExpiration]', [
                            'merchant_offer_id' => $merchantOfferWithoutRedeem->merchant_offer_id,
                            'user_id' => $merchantOfferWithoutRedeem->user_id,
                            'days_left' => $daysLeft,
                        ]);
                        }
                    }
                }
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            Log::error('[NotifyMerchantOffersRedeemExpiration] Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
