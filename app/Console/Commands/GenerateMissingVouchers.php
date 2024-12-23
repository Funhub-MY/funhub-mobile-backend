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
                    // Generate voucher data
                    $voucherData = [];
                    for ($i = 0; $i < $missingCount; $i++) {
                        $voucherData[] = [
                            'merchant_offer_id' => $offer->id,
                            'code' => MerchantOfferVoucher::generateCode(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    // Insert vouchers
                    MerchantOfferVoucher::insert($voucherData);

                    $this->info("Generated {$missingCount} vouchers for merchant offer ID: {$offer->id}");
                    Log::info("Successfully generated {$missingCount} vouchers for merchant offer ID: {$offer->id}");
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
