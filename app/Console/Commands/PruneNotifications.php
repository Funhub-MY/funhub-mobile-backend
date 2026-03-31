<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune {--days=30 : Number of days to keep} {--chunk=1000 : Number of records to delete per chunk}';

    protected $description = 'Prune notifications older than specified days using chunked deletion';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $chunkSize = (int) $this->option('chunk');

        if ($days < 1) {
            $this->error('The --days option must be at least 1.');

            return Command::FAILURE;
        }

        if ($chunkSize < 1) {
            $this->error('The --chunk option must be at least 1.');

            return Command::FAILURE;
        }

        $cutoffDate = now()->subDays($days);

        $this->info("Starting notifications prune: deleting records before {$cutoffDate} (keep last {$days} days).");
        $this->info("Using chunk size: {$chunkSize}");

        $totalCount = DB::table('notifications')
            ->where('created_at', '<', $cutoffDate)
            ->count();

        if ($totalCount === 0) {
            $this->info('No notifications to prune.');

            return Command::SUCCESS;
        }

        $this->info("Found {$totalCount} notification(s) to delete.");

        $totalDeleted = 0;
        $hasMore = true;

        while ($hasMore) {
            $ids = DB::table('notifications')
                ->where('created_at', '<', $cutoffDate)
                ->orderBy('id')
                ->limit($chunkSize)
                ->pluck('id')
                ->toArray();

            if (empty($ids)) {
                $hasMore = false;
                break;
            }

            $deleted = DB::table('notifications')
                ->whereIn('id', $ids)
                ->delete();

            $totalDeleted += $deleted;

            usleep(100000);
        }

        $this->info("Successfully deleted {$totalDeleted} notification(s).");

        return Command::SUCCESS;
    }
}
