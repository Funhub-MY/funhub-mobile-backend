<?php

namespace App\Console\Commands;

use App\Models\MerchantOfferVoucher;
use App\Services\MixpanelService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncVoucherSalesToMixpanel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mixpanel:sync-voucher-sales 
                            {--days=1 : Number of days to look back for voucher sales}
                            {--all : Sync all voucher sales regardless of date}
                            {--limit=100 : Maximum number of records to process per batch}
                            {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync today\'s voucher sales data to Mixpanel by default';

    /**
     * @var MixpanelService
     */
    protected $mixpanelService;

    /**
     * Create a new command instance.
     *
     * @param MixpanelService $mixpanelService
     * @return void
     */
    public function __construct(MixpanelService $mixpanelService)
    {
        parent::__construct();
        $this->mixpanelService = $mixpanelService;
    }

    /**
     * execute the console command
     *
     * @return int
     */
    public function handle()
    {
        $this->info('starting voucher sales sync to mixpanel...');
        
        $limit = (int) $this->option('limit');
        $days = (int) $this->option('days');
        $all = (bool) $this->option('all');
        $dryRun = (bool) $this->option('dry-run');
        
        if ($dryRun) {
            $this->comment('running in dry-run mode - no data will be sent to mixpanel');
        }
        
        try {
            // build the query for vouchers with successful claims (sales)
            $query = MerchantOfferVoucher::whereNotNull('owned_by_id')
                ->with([
                    'merchant_offer', 
                    'merchant_offer.user', 
                    'merchant_offer.user.merchant', 
                    'claim',
                    'claim.user'
                ])
                ->whereHas('claim', function ($query) {
                    $query->where('status', '=', 1); // only successful claims
                });
            
            // apply date filter if not syncing all
            if (!$all) {
                $startDate = Carbon::now()->subDays($days);
                $this->info("syncing voucher sales from {$startDate->format('Y-m-d')} to now");
                
                $query->whereHas('claim', function ($query) use ($startDate) {
                    $query->where('created_at', '>=', $startDate);
                });
            } else {
                $this->info("syncing all voucher sales - this might take a while");
            }
            
            // get total count for progress display
            $totalVouchers = $query->count();
            $this->info("found {$totalVouchers} vouchers to sync");
            
            if ($totalVouchers === 0) {
                $this->info("no vouchers found to sync. exiting.");
                return Command::SUCCESS;
            }
            
            // process in chunks to avoid memory issues
            $processed = 0;
            $successful = 0;
            $failed = 0;
            $failedIds = [];
            
            $query->chunkById($limit, function ($vouchers) use (&$processed, &$successful, &$failed, &$failedIds, $dryRun, $totalVouchers) {
                foreach ($vouchers as $voucher) {
                    $processed++;
                    
                    try {
                        $result = $this->mixpanelService->trackVoucherSale($voucher, $dryRun);
                        sleep(1);
                        
                        if ($result) {
                            $successful++;
                        } else {
                            $failed++;
                            $failedIds[] = $voucher->id;
                            $this->error("failed to sync voucher ID: {$voucher->id}");
                        }
                    } catch (\Exception $e) {
                        $failed++;
                        $failedIds[] = $voucher->id;
                        $this->error("error syncing voucher ID {$voucher->id}: {$e->getMessage()}");
                        Log::error("error syncing voucher to mixpanel", [
                            'voucher_id' => $voucher->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                    
                    if ($processed % 10 === 0) {
                        $this->info("processed {$processed}/{$totalVouchers} vouchers ({$successful} successful, {$failed} failed)");
                    }
                }
            });
            
            $this->newLine();
            $this->info("completed syncing voucher sales to mixpanel");
            $this->info("total processed: {$processed}");
            $this->info("successful: {$successful}");
            $this->info("failed: {$failed}");
            
            if ($failed > 0) {
                $this->warning("failed voucher IDs: " . implode(', ', array_slice($failedIds, 0, 10)) . 
                    (count($failedIds) > 10 ? " and " . (count($failedIds) - 10) . " more" : ""));
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("command failed: {$e->getMessage()}");
            Log::error("voucher sales sync command failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }
}
