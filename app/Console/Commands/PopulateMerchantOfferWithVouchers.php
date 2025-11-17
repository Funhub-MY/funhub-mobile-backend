<?php

namespace App\Console\Commands;

use App\Models\MerchantOffer;
use App\Models\MerchantOfferVoucher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PopulateMerchantOfferWithVouchers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'merchant-offers:populate-vouchers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // find merchant offers with quantity but zero vouchers as voucher represent per quantity of stock
        $offers = MerchantOffer::where('quantity', '>', 0)
            ->whereDoesntHave('vouchers')
            ->get();

        foreach ($offers as $offer) {
            $quantity = $offer->quantity;
            
            // Validate against agreement_quantity if offer has a campaign
            if ($offer->merchant_offer_campaign_id && $offer->campaign) {
                $campaign = $offer->campaign;
                if ($campaign->agreement_quantity > 0) {
                    $currentVoucherCount = MerchantOfferVoucher::whereHas('merchant_offer', function ($query) use ($campaign) {
                        $query->where('merchant_offer_campaign_id', $campaign->id);
                    })->count();
                    
                    $maxAllowed = $campaign->agreement_quantity - $currentVoucherCount;
                    $quantity = min($quantity, $maxAllowed);
                    
                    if ($quantity <= 0) {
                        Log::warning('[PopulateMerchantOfferWithVouchers] Cannot create vouchers - agreement quantity reached', [
                            'campaign_id' => $campaign->id,
                            'agreement_quantity' => $campaign->agreement_quantity,
                            'current_vouchers' => $currentVoucherCount,
                            'offer_id' => $offer->id,
                        ]);
                        $this->warn("Skipping offer {$offer->id} - agreement quantity reached");
                        continue;
                    }
                }
            }
            
            // Process vouchers in chunks for better performance
            $chunkSize = 500;
            $now = now();
            $totalCreated = 0;
            
            for ($chunk = 0; $chunk < $quantity; $chunk += $chunkSize) {
                $chunkQuantity = min($chunkSize, $quantity - $chunk);
                $voucherData = [];
                
                for ($i = 0; $i < $chunkQuantity; $i++) {
                    $voucherData[] = [
                        'merchant_offer_id' => $offer->id,
                        'code' => MerchantOfferVoucher::generateCode(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                
                if (!empty($voucherData)) {
                    MerchantOfferVoucher::insert($voucherData);
                    $totalCreated += count($voucherData);
                }
            }
            
            Log::info('[PopulateMerchantOfferWithVouchers] Created vouchers for merchant offer', [
                'merchant_offer_id' => $offer->id,
                'quantity_created' => $totalCreated,
            ]);
            $this->info("Created {$totalCreated} vouchers for merchant offer {$offer->id}");
        }
        
        return Command::SUCCESS;
    }
}
