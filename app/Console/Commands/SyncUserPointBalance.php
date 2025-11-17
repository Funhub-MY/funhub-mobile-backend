<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PointService;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Console\Command;

class SyncUserPointBalance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:sync-point-balance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync user point balance from point_ledgers table to users table point_balance field.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // remap all user point balance from point_ledgers table
        $users = User::whereHas('pointLedgers')->get();
        $this->info('Total users to sync with point ledgers: ' . $users->count());
        $pointService = new PointService();
        foreach ($users as $user) {
            try {
                $pointBalance = $pointService->getBalanceOfUser($user);
                if ($pointBalance != $user->point_balance) {
                    $this->info('Remapping user point balance: ' . $user->id . ' from ' . $user->point_balance . ' to ' . $pointBalance);
                }
                $user->point_balance = $pointBalance;
                $user->save();

                $this->info('Synced user point balance: ' . $user->id . ' to ' . $pointBalance);
                Log::info('Synced user point balance: ' . $user->id . ' to ' . $pointBalance);
            } catch (Exception $e) {
                Log::error('Failed to sync user point balance: ' . $e->getMessage());
            }
        }
        return Command::SUCCESS;
    }
}
