<?php

namespace App\Console\Commands;

use Exception;
use App\Models\User;
use App\Services\MixpanelService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncUserDataToMixpanel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mixpanel:sync-user-data 
                            {--days=1 : Number of days to look back for new/updated users}
                            {--all : Sync all users regardless of date}
                            {--user_id= : Sync a specific user by ID}
                            {--limit=100 : Maximum number of records to process per batch}
                            {--status= : Filter by status (active, restricted, suspended)}
                            {--source= : Filter by source (organic, referral)}
                            {--dry-run : Run without actually sending data to Mixpanel}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync user data to Mixpanel for analytics';

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
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting user data sync to Mixpanel...');
        
        $limit = (int) $this->option('limit');
        $days = (int) $this->option('days');
        $all = (bool) $this->option('all');
        $dryRun = (bool) $this->option('dry-run');
        $status = $this->option('status');
        $source = $this->option('source');
        $userId = $this->option('user_id');
        
        if ($dryRun) {
            $this->comment('Running in dry-run mode - no data will be sent to Mixpanel');
        }
        
        try {
            // Build the query for users
            $query = User::with(['referredBy']);
            
            // If specific user_id is provided, only sync that user
            if ($userId) {
                $query->where('id', $userId);
                $this->info("Syncing specific user with ID: {$userId}");
            }
            // Apply date filter if not syncing all and no specific user_id
            else if (!$all) {
                $startDate = Carbon::now()->subDays($days);
                $this->info("Syncing users updated from {$startDate->format('Y-m-d')} to now");
                $query->where('updated_at', '>=', $startDate);
            } else {
                $this->info("Syncing all users - this might take a while");
            }
            
            // Apply status filter if provided
            if ($status) {
                if ($status === 'active') {
                    $query->where('status', User::STATUS_ACTIVE)
                          ->where(function($q) {
                              $q->whereNull('account_restricted_until')
                                ->orWhere('account_restricted_until', '<', now());
                          })
                          ->where(function($q) {
                              $q->whereNull('suspended_until')
                                ->orWhere('suspended_until', '<', now());
                          });
                } elseif ($status === 'restricted') {
                    $query->where('account_restricted', true)
                          ->where('account_restricted_until', '>=', now());
                } elseif ($status === 'suspended') {
                    $query->where(function($q) {
                        $q->where('status', User::STATUS_SUSPENDED)
                          ->orWhere('suspended_until', '>=', now());
                    });
                }
                $this->info("Filtering users with status: {$status}");
            }
            
            // Apply source filter if provided
            if ($source) {
                if ($source === 'organic') {
                    $query->whereNull('referred_by_id');
                    $this->info("Filtering organic users (no referral)");
                } elseif ($source === 'referral') {
                    $query->whereNotNull('referred_by_id');
                    $this->info("Filtering users with referrals");
                }
            }
            
            // Get total count for progress display
            $totalUsers = $query->count();
            $this->info("Found {$totalUsers} users to sync");
            
            if ($totalUsers === 0) {
                $this->info("No users found to sync. Exiting.");
                return Command::SUCCESS;
            }
            
            // Process in chunks to avoid memory issues
            $processed = 0;
            $successful = 0;
            $failed = 0;
            $failedIds = [];
            
            $query->chunkById($limit, function ($users) use (&$processed, &$successful, &$failed, &$failedIds, $dryRun, $totalUsers) {
                foreach ($users as $user) {
                    $processed++;
                    
                    try {
                        $result = $this->mixpanelService->trackUserData($user, $dryRun);
                        sleep(1);
                        
                        if ($result) {
                            $successful++;
                        } else {
                            $failed++;
                            $failedIds[] = $user->id;
                            $this->error("Failed to sync user ID: {$user->id}");
                        }
                    } catch (Exception $e) {
                        $failed++;
                        $failedIds[] = $user->id;
                        $this->error("Error syncing user ID {$user->id}: {$e->getMessage()}");
                        Log::error("Error syncing user to Mixpanel", [
                            'user_id' => $user->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                    
                    if ($processed % 10 === 0 || $processed === $totalUsers) {
                        $percentage = round(($processed / $totalUsers) * 100, 2);
                        $this->info("Processed {$processed}/{$totalUsers} users ({$percentage}%) - {$successful} successful, {$failed} failed");
                    }
                }
            });
            
            $this->newLine();
            $this->info("Completed syncing user data to Mixpanel");
            $this->info("Total processed: {$processed}");
            $this->info("Successful: {$successful}");
            $this->info("Failed: {$failed}");
            
            if ($failed > 0) {
                $this->warning("Failed user IDs: " . implode(', ', array_slice($failedIds, 0, 10)) . 
                    (count($failedIds) > 10 ? " and " . (count($failedIds) - 10) . " more" : ""));
            }
            
            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("Command failed: {$e->getMessage()}");
            Log::error("User data sync command failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }
}
