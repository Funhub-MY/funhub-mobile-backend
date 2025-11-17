<?php

namespace App\Console\Commands;

use Exception;
use App\Models\VideoJob;
use App\Services\ByteplusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateByteplusVideoMasterAbr extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'byteplus:update-master-abr';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update master ABR links for completed Byteplus video jobs';

    /**
     * Execute the console command.
     */
    public function handle(ByteplusService $byteplusService)
    {
        $jobs = VideoJob::where('status', VideoJob::STATUS_COMPLETED)
            ->where('provider', 'byteplus')
            ->whereRaw("JSON_EXTRACT(results, '$.playback_links.master_abr') IS NULL")
            ->get();

        $this->info("Found {$jobs->count()} video jobs to update");

        foreach ($jobs as $job) {
            $this->info("Processing video job ID: {$job->id}, Media ID: {$job->media_id}");
            
            try {
                $vid = $job->results['vid'] ?? null;
                if (!$vid) {
                    $this->error("No VID found for job ID: {$job->id}");
                    continue;
                }

                $playbackInfo = $byteplusService->getPlayInfo($vid);
                if (empty($playbackInfo)) {
                    $this->error("Failed to get playback info for VID: {$vid}, job ID: {$job->id}");
                    continue;
                }

                $job->update([
                    'results' => array_merge(
                        $job->results,
                        [
                            'playback_links' => $playbackInfo
                        ]
                    )
                ]);

                $this->info("Successfully updated video job ID: {$job->id}");
                $this->line("New playback links: " . json_encode($playbackInfo, JSON_PRETTY_PRINT));

            } catch (Exception $e) {
                $this->error("Error processing job ID {$job->id}: " . $e->getMessage());
                Log::error('Error updating video job master ABR', [
                    'job_id' => $job->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info('Update completed');
    }
}
