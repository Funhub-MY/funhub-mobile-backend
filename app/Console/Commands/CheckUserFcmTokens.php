<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckUserFcmTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:check-fcm-tokens {user_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check if users have duplicate FCM tokens';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $userId = $this->argument('user_id');

        if ($userId) {
            // Check specific user
            $user = User::find($userId);
            if (!$user) {
                $this->error("User with ID {$userId} not found");
                return 1;
            }

            $this->info("User: {$user->name} (ID: {$user->id})");
            $this->info("FCM Token: " . ($user->fcm_token ?? 'None'));
            
            // Check if other users have the same FCM token
            if ($user->fcm_token) {
                $usersWithSameToken = User::where('fcm_token', $user->fcm_token)
                    ->where('id', '!=', $user->id)
                    ->get();
                
                if ($usersWithSameToken->count() > 0) {
                    $this->warn("Found {$usersWithSameToken->count()} other users with the same FCM token:");
                    foreach ($usersWithSameToken as $otherUser) {
                        $this->line("- {$otherUser->name} (ID: {$otherUser->id})");
                    }
                } else {
                    $this->info("No other users have the same FCM token");
                }
            }
        } else {
            // Check all users with duplicate tokens
            $this->info("Checking for users with duplicate FCM tokens...");
            
            $duplicateTokens = DB::table('users')
                ->select('fcm_token', DB::raw('COUNT(*) as count'))
                ->whereNotNull('fcm_token')
                ->groupBy('fcm_token')
                ->having('count', '>', 1)
                ->get();
            
            if ($duplicateTokens->count() > 0) {
                $this->warn("Found {$duplicateTokens->count()} FCM tokens used by multiple users:");
                
                foreach ($duplicateTokens as $token) {
                    $this->info("Token: {$token->fcm_token} (Used by {$token->count} users)");
                    
                    $users = User::where('fcm_token', $token->fcm_token)->get();
                    foreach ($users as $user) {
                        $this->line("- {$user->name} (ID: {$user->id})");
                    }
                    
                    $this->newLine();
                }
            } else {
                $this->info("No duplicate FCM tokens found");
            }
            
            // Count users with null FCM tokens
            $nullTokenCount = User::whereNull('fcm_token')->count();
            $this->info("Users with null FCM token: {$nullTokenCount}");
        }

        return 0;
    }
}
