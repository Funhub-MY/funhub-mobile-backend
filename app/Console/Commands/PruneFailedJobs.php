<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneFailedJobs extends Command
{
    protected $signature = 'failed-jobs:prune {--months=3 : Number of months to keep} {--chunk=1000 : Number of records to delete per chunk}';
    
    protected $description = 'Prune failed jobs older than specified months using chunked deletion';

    public function handle()
    {
        $months = $this->option('months');
        $chunkSize = $this->option('chunk');
        $date = now()->subMonths($months);
        
        $this->info("Starting to prune failed jobs older than {$months} months (before {$date})...");
        $this->info("Using chunk size: {$chunkSize}");
        
        $totalDeleted = 0;
        $bar = null;
        
        // First, get the total count for progress tracking
        $totalCount = DB::table('failed_jobs')
            ->where('failed_at', '<', $date)
            ->count();
        
        if ($totalCount === 0) {
            $this->info('No failed jobs to prune.');
            return Command::SUCCESS;
        }
        
        $this->info("Found {$totalCount} failed job(s) to delete.");
        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();
        
        // Delete in chunks
        do {
            $deleted = DB::table('failed_jobs')
                ->where('failed_at', '<', $date)
                ->limit($chunkSize)
                ->delete();
            
            $totalDeleted += $deleted;
            $bar->advance($deleted);
            
            // Small delay to prevent overwhelming the database
            if ($deleted > 0) {
                usleep(100000); // 0.1 second delay
            }
            
        } while ($deleted > 0);
        
        $bar->finish();
        $this->newLine(2);
        $this->info("Successfully deleted {$totalDeleted} failed job(s).");
        
        // Show remaining count
        $remaining = DB::table('failed_jobs')->count();
        $this->info("Remaining failed jobs in table: {$remaining}");
        
        return Command::SUCCESS;
    }
}