<?php

namespace App\Console\Commands;

use App\Models\VideoJob;
use App\Services\ByteplusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RefreshExpiredVideoPlaybackLinks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'byteplus:refresh-playback-links 
        {--limit=50 : Maximum number of jobs to process} 
        {--force : Force refresh all links regardless of expiration} 
        {--dry-run : Run without making changes}
        {--date= : Filter by creation date (YYYY-MM-DD)}
        {--from= : Filter from this date (YYYY-MM-DD)}
        {--to= : Filter to this date (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find video jobs with status 3 and refresh their expired playback links';

    /**
     * Execute the console command.
     */
    public function handle(ByteplusService $byteplusService)
    {
        $limit = $this->option('limit');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $date = $this->option('date');
        $fromDate = $this->option('from');
        $toDate = $this->option('to');

        $dateInfo = '';
        if ($date) {
            $dateInfo = ", date: {$date}";
        } elseif ($fromDate || $toDate) {
            $dateInfo = ($fromDate ? ", from: {$fromDate}" : '') . ($toDate ? ", to: {$toDate}" : '');
        }

        $this->line('┌──────────────────────────────────────────────────────────────┐');
        $this->line('│ <fg=blue;options=bold>REFRESH EXPIRED VIDEO PLAYBACK LINKS</> <fg=gray>v1.0</fg=gray>                │');
        $this->line('└──────────────────────────────────────────────────────────────┘');
        $this->line('');
        $this->info("<fg=yellow>▶</> Starting with parameters:");
        $this->line("  • <fg=white>Limit:</> {$limit} jobs");
        $this->line("  • <fg=white>Force refresh:</> " . ($force ? '<fg=green>Yes</>' : '<fg=red>No</>'));
        $this->line("  • <fg=white>Dry run:</> " . ($dryRun ? '<fg=yellow>Yes (no changes will be saved)</>' : '<fg=green>No (changes will be saved)</>'));
        if ($date) {
            $this->line("  • <fg=white>Date filter:</> {$date}");
        } elseif ($fromDate || $toDate) {
            $this->line("  • <fg=white>Date range:</> " . ($fromDate ?: 'any') . ' to ' . ($toDate ?: 'any'));
        }
        $this->line('');

        // Get completed video jobs
        $query = VideoJob::where('status', VideoJob::STATUS_COMPLETED)
            ->where('provider', 'byteplus')
            ->whereNotNull('results');

        // Apply date filters if provided
        if ($date) {
            $query->whereDate('created_at', $date);
        } else {
            if ($fromDate) {
                $query->whereDate('created_at', '>=', $fromDate);
            }
            if ($toDate) {
                $query->whereDate('created_at', '<=', $toDate);
            }
        }

        $videoJobs = $query->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();

        $jobCount = $videoJobs->count();
        $this->info("<fg=yellow>▶</> Found {$jobCount} completed video jobs to check");
        $this->line('');

        $refreshed = 0;
        $skipped = 0;
        $failed = 0;
        $refreshedJobs = [];
        
        $progressBar = $this->output->createProgressBar($jobCount);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        foreach ($videoJobs as $videoJob) {
            $progressBar->advance();
            
            // Clear the current line for detailed output
            $this->output->write("\r");
            $this->output->write("\033[K");
            
            $this->line("\n<fg=blue>⬤</> Processing job ID: <fg=white;options=bold>{$videoJob->id}</>, media ID: {$videoJob->media_id}");
            
            // Extract the vid from results
            $vid = $videoJob->results['vid'] ?? null;
            
            if (!$vid) {
                $this->line("  <fg=red>✗</> No vid found, skipping");
                $skipped++;
                continue;
            }
            
            // Check if playback links exist and need refreshing
            $currentLinks = $videoJob->results['playback_links'] ?? [];
            $needsRefresh = $force; // If force option is true, always refresh
            
            if (empty($currentLinks)) {
                $this->info("No playback links found for job ID: {$videoJob->id}, will create new ones");
                $needsRefresh = true;
            } elseif (!$needsRefresh) {
                // Check if links are expired by trying to access them
                $masterAbrLink = $currentLinks['master_abr'] ?? '';
                $abrLink = $currentLinks['abr'] ?? '';
                
                if (empty($masterAbrLink) || empty($abrLink)) {
                    $this->line("  <fg=yellow>⚠</> Missing one or more playback links, will refresh");
                    $needsRefresh = true;
                } else {
                    // Try to access the master ABR link to check if it's expired
                    $this->line("  <fg=blue>ℹ</> Checking if master ABR link is expired");
                    
                    try {
                        $response = Http::timeout(5)->get($masterAbrLink);
                        
                        if ($response->successful()) {
                            $content = $response->body();
                            
                            // Check if the response indicates the link is expired
                            if (strpos($content, 'expired') !== false || strpos($content, 'time') !== false) {
                                $this->line("  <fg=yellow>⚠</> Link is expired, will refresh");
                                $needsRefresh = true;
                            } else {
                                $this->line("  <fg=green>✓</> Link is still valid");
                            }
                        } else {
                            // If we get an error response, the link is likely expired
                            $this->line("  <fg=yellow>⚠</> Link returned HTTP status {$response->status()}, will refresh");
                            $needsRefresh = true;
                        }
                    } catch (\Exception $e) {
                        $this->line("  <fg=red>✗</> Exception while checking link: {$e->getMessage()}");
                        $this->line("  <fg=yellow>⚠</> Will refresh due to error");
                        $needsRefresh = true;
                    }
                }
            }
            
            if ($needsRefresh) {
                $this->line("  <fg=yellow>▶</> Refreshing playback links for vid: <fg=white;options=bold>{$vid}</>");
                
                if (!$dryRun) {
                    try {
                        // Get fresh playback links
                        $playbackLinks = $byteplusService->getPlayInfo($vid);
                        
                        if (!empty($playbackLinks) && isset($playbackLinks['abr']) && isset($playbackLinks['master_abr'])) {
                            // Update the results
                            $results = $videoJob->results;
                            $results['playback_links'] = $playbackLinks;
                            
                            $videoJob->results = $results;
                            $videoJob->save();
                            
                            $this->line("  <fg=green>✓</> Successfully refreshed playback links");
                            $this->line("  <fg=white;options=bold>NEW LINKS:</>");
                            $this->line("  <fg=blue>ABR:</> <fg=cyan>" . $playbackLinks['abr'] . "</fg=cyan>");
                            $this->line("  <fg=blue>MASTER:</> <fg=cyan>" . $playbackLinks['master_abr'] . "</fg=cyan>");
                            
                            // Log the new links
                            Log::info("Refreshed playback links for video job", [
                                'job_id' => $videoJob->id,
                                'vid' => $vid,
                                'media_id' => $videoJob->media_id,
                                'abr_link' => $playbackLinks['abr'],
                                'master_abr_link' => $playbackLinks['master_abr']
                            ]);
                            
                            // Store information about the refreshed job
                            $refreshedJobs[] = [
                                'id' => $videoJob->id,
                                'vid' => $vid,
                                'created_at' => $videoJob->created_at,
                                'media_id' => $videoJob->media_id
                            ];
                            
                            $refreshed++;
                        } else {
                            $this->line("  <fg=red>✗</> Failed to get valid playback links");
                            $failed++;
                        }
                    } catch (\Exception $e) {
                        $this->line("  <fg=red>✗</> Exception: " . $e->getMessage());
                        Log::error("Exception while refreshing playback links", [
                            'job_id' => $videoJob->id,
                            'vid' => $vid,
                            'error' => $e->getMessage()
                        ]);
                        $failed++;
                    }
                } else {
                    $this->line("  <fg=yellow>▶</> DRY RUN - would refresh playback links");
                    // In dry run mode, try to get the links anyway to show what they would be
                    try {
                        $playbackLinks = $byteplusService->getPlayInfo($vid);
                        if (!empty($playbackLinks) && isset($playbackLinks['abr']) && isset($playbackLinks['master_abr'])) {
                            $this->line("  <fg=white;options=bold>NEW LINKS WOULD BE:</>");
                            $this->line("  <fg=blue>ABR:</> <fg=cyan>" . $playbackLinks['abr'] . "</fg=cyan>");
                            $this->line("  <fg=blue>MASTER:</> <fg=cyan>" . $playbackLinks['master_abr'] . "</fg=cyan>");
                            
                            // Store information about the refreshed job (dry run)
                            $refreshedJobs[] = [
                                'id' => $videoJob->id,
                                'vid' => $vid,
                                'created_at' => $videoJob->created_at,
                                'media_id' => $videoJob->media_id,
                                'dry_run' => true
                            ];
                        }
                    } catch (\Exception $e) {
                        $this->line("  <fg=red>✗</> Could not get sample links: " . $e->getMessage());
                    }
                    $refreshed++;
                }
            } else {
                $this->line("  <fg=green>✓</> Links are still valid, skipping");
                $skipped++;
            }
        }
        
        // Finish the progress bar
        $progressBar->finish();
        $this->line("\n");
        
        // Display summary
        $this->line('┌──────────────────────────────────────────────────────────────┐');
        $this->line('│ <fg=blue;options=bold>SUMMARY</fg=blue;options=bold>                                                     │');
        $this->line('└──────────────────────────────────────────────────────────────┘');
        $this->line(" <fg=green>✓</> Completed refreshing expired video playback links");
        $this->line(" <fg=blue>ℹ</> Total jobs processed: {$jobCount}");
        $this->line(" <fg=green>✓</> Refreshed: {$refreshed}");
        $this->line(" <fg=yellow>⚠</> Skipped: {$skipped}");
        if ($failed > 0) {
            $this->line(" <fg=red>✗</> Failed: {$failed}");
        }
        $this->line('');
        
        // Display detailed information about refreshed videos if any
        if (count($refreshedJobs) > 0) {
            $this->line('┌──────────────────────────────────────────────────────────────┐');
            $this->line('│ <fg=blue;options=bold>REFRESHED VIDEOS</fg=blue;options=bold>                                              │');
            $this->line('└──────────────────────────────────────────────────────────────┘');
            
            // Group by creation date
            $groupedByDate = [];
            foreach ($refreshedJobs as $job) {
                $date = $job['created_at']->format('Y-m-d');
                if (!isset($groupedByDate[$date])) {
                    $groupedByDate[$date] = [];
                }
                $groupedByDate[$date][] = $job;
            }
            
            // Sort dates in descending order (newest first)
            krsort($groupedByDate);
            
            foreach ($groupedByDate as $date => $jobs) {
                $this->line(" <fg=yellow>▶</> <fg=white;options=bold>{$date}</> - {$this->formatCount(count($jobs))}");
                
                // Sort jobs by ID within each date group
                usort($jobs, function($a, $b) {
                    return $b['id'] <=> $a['id']; // Descending order by ID
                });
                
                foreach ($jobs as $job) {
                    $isDryRun = isset($job['dry_run']) && $job['dry_run'] ? ' <fg=yellow>(dry run)</>' : '';
                    $this->line("   <fg=blue>•</> ID: <fg=white>{$job['id']}</>, VID: <fg=white>{$job['vid']}</>, Media: <fg=white>{$job['media_id']}</>{$isDryRun}");
                }
            }
            $this->line('');
        }
        
        return Command::SUCCESS;
    }
    
    // This method is no longer used as we're displaying full links now
    
    /**
     * Format a count with appropriate suffix
     *
     * @param int $count
     * @return string
     */
    private function formatCount(int $count): string
    {
        return $count . ($count === 1 ? ' video' : ' videos');
    }
}
