<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\AccountUnrestrictedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckAccountRestrictions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:check-account-restrictions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and update account restrictions based on restriction end date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('[CheckAccountRestrictions]Checking account restrictions...');
        Log::info('[CheckAccountRestrictions]Checking account restrictions...');

        try {
            // Find users with account_restricted = true and account_restricted_until date in the past
            $users = User::where('account_restricted', true)
                ->whereNotNull('account_restricted_until')
                ->where('account_restricted_until', '<', now())
                ->get();
            
            $count = $users->count();
            $this->info("[CheckAccountRestrictions]Found {$count} users with expired account restrictions");
            Log::info("[CheckAccountRestrictions]Found {$count} users with expired account restrictions");
            
            foreach ($users as $user) {
                // Log the user being updated
                $this->info("[CheckAccountRestrictions]Removing account restriction for user: {$user->id} - {$user->name} (restricted until: {$user->account_restricted_until})");
                
                // Update the user's account_restricted status to false
                $user->account_restricted = false;
                $user->save();
            }
            
            $this->info('[CheckAccountRestrictions]Account restrictions check completed successfully');
            Log::info('[CheckAccountRestrictions]Account restrictions check completed successfully');
            return 0;
        } catch (\Exception $e) {
            $this->error("[CheckAccountRestrictions]Error checking account restrictions: {$e->getMessage()}");
            Log::error("[CheckAccountRestrictions]Error in CheckAccountRestrictions command", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}
