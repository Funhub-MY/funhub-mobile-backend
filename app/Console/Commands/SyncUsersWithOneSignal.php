<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\OneSignalService;
use Illuminate\Console\Command;

class SyncUsersWithOneSignal extends Command
{
    protected $signature = 'onesignal:sync-users {--offset=} {--limit=} {--user-id=}';

    protected $description = 'Sync users with OneSignal';

    public function handle()
    {
        $offset = $this->option('offset') ?: 0;
        $limit = $this->option('limit') ?: 100;
        $userId = $this->option('user-id');

        $oneSignalService = new OneSignalService();

        if ($userId) {
            $user =  User::where('id', $userId)->where('status', 1)->first();
            if ($user) {
                $this->info("Syncing user with ID: {$userId}");
                $oneSignalService->syncUser($user);
                $this->info("User with ID: {$userId} synced successfully.");
            } else {
                $this->error("User with ID: {$userId} not found.");
            }
        } else {
            $users = User::where('status', 1)->skip($offset)->limit($limit)->get();
            $this->info("Syncing {$users->count()} users with OneSignal...");
            $oneSignalService->bulkSyncUsers($users);
            $this->info('Users synced successfully.');
        }
    }
}
