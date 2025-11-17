<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferVoucher;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GenerateMissingVouchers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vouchers:generate-missing {start_date : Date to start checking from (Y-m-d)} {--dry-run : Check without generating vouchers}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate missing vouchers for merchant offers created after specified date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDate = Carbon::parse($this->argument('start_date'));
        $isDryRun = $this->option('dry-run');
        
        $this->info("Starting to check merchant offers created after " . $startDate->format('Y-m-d'));
        $this->info($isDryRun ? "DRY RUN MODE - No vouchers will be generated" : "LIVE MODE - Vouchers will be generated");
        Log::info("Starting missing vouchers check from date: " . $startDate . ($isDryRun ? " (DRY RUN)" : " (LIVE)"));

        // Get all merchant offers after the start date
        $merchantOffers = MerchantOffer::where('created_at', '>=', $startDate)
            ->withCount('vouchers')
            ->get();

        $totalFixed = 0;
        $totalVouchersGenerated = 0;

        foreach ($merchantOffers as $offer) {
            // Check if vouchers count matches quantity
            if ($offer->vouchers_count < $offer->quantity) {
                $missingCount = $offer->quantity - $offer->vouchers_count;
                
                $logMessage = sprintf(
                    "Merchant offer ID: %d, SKU: %s, Missing: %d vouchers, Schedule: %s -> %s",
                    $offer->id,
                    $offer->sku,
                    $missingCount,
                    $offer->available_at ? Carbon::parse($offer->available_at)->format('Y-m-d H:i:s') : 'N/A',
                    $offer->available_until ? Carbon::parse($offer->available_until)->format('Y-m-d H:i:s') : 'N/A'
                );

                $this->info($logMessage);
                Log::info($logMessage);

                if (!$isDryRun) {
                    $quantityToCreate = $missingCount;
                    
                    // Validate against agreement_quantity if offer has a campaign
                    if ($offer->merchant_offer_campaign_id && $offer->campaign) {
                        $campaign = $offer->campaign;
                        if ($campaign->agreement_quantity > 0) {
                            $currentVoucherCount = MerchantOfferVoucher::whereHas('merchant_offer', function ($query) use ($campaign) {
                                $query->where('merchant_offer_campaign_id', $campaign->id);
                            })->count();
                            
                            $maxAllowed = $campaign->agreement_quantity - $currentVoucherCount;
                            $quantityToCreate = min($missingCount, $maxAllowed);
                            
                            if ($quantityToCreate <= 0) {
                                Log::warning('[GenerateMissingVouchers] Cannot create vouchers - agreement quantity reached', [
                                    'campaign_id' => $campaign->id,
                                    'agreement_quantity' => $campaign->agreement_quantity,
                                    'current_vouchers' => $currentVoucherCount,
                                    'offer_id' => $offer->id,
                                    'missing_count' => $missingCount,
                                ]);
                                $this->warn("Skipping offer {$offer->id} - agreement quantity reached (missing: {$missingCount})");
                                continue;
                            }
                            
                            if ($quantityToCreate < $missingCount) {
                                $this->warn("Only creating {$quantityToCreate} vouchers instead of {$missingCount} due to agreement limit");
                            }
                        }
                    }
                    
                    // Process vouchers in chunks for better performance
                    $chunkSize = 500;
                    $now = now();
                    $totalCreated = 0;
                    
                    for ($chunk = 0; $chunk < $quantityToCreate; $chunk += $chunkSize) {
                        $chunkQuantity = min($chunkSize, $quantityToCreate - $chunk);
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

                    $this->info("Generated {$totalCreated} vouchers for merchant offer ID: {$offer->id}");
                    Log::info("Successfully generated {$totalCreated} vouchers for merchant offer ID: {$offer->id}");
                }

                $totalFixed++;
                $totalVouchersGenerated += $missingCount;
            }
        }

        $this->info("\nProcess completed!");
        $this->info("Total merchant offers requiring fixes: {$totalFixed}");
        $this->info("Total vouchers " . ($isDryRun ? "needed" : "generated") . ": {$totalVouchersGenerated}");
        
        Log::info(
            "Missing vouchers " . ($isDryRun ? "check" : "generation") . " completed. " .
            "Found {$totalFixed} offers, " . ($isDryRun ? "needs" : "generated") . " {$totalVouchersGenerated} vouchers"
        );
    }
}
