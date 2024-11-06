<?php

namespace App\Console\Commands;

use App\Models\VideoJob;
use App\Services\ByteplusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckByteplusVideoStatus extends Command
{
    protected $signature = 'byteplus:check-video-status';
    protected $description = 'Check status of BytePlus video processing jobs';

    public function handle(ByteplusService $byteplusService): void
    {
        // Process uploading videos
        $this->checkUploadingVideos($byteplusService);

        // Process videos in workflow
        $this->checkProcessingVideos($byteplusService);
    }

    /**
     * Check uploading videos
     *
     * @param ByteplusService $byteplusService
     * @return void
     */
    private function checkUploadingVideos(ByteplusService $byteplusService): void
    {
        VideoJob::where('status', VideoJob::STATUS_UPLOADING)
            ->chunk(10, function ($jobs) use ($byteplusService) {
                foreach ($jobs as $job) {
                    $this->info("Checking upload status for job: {$job->job_id}");

                    $result = $byteplusService->queryUploadTaskInfo($job->job_id);

                    $this->info("Results for job: {$job->job_id}: " . json_encode($result));

                    if (empty($result)) {
                        continue;
                    }

                    $mediaInfo = $result['MediaInfoList'][0] ?? null;
                    if (!$mediaInfo) {
                        continue;
                    }

                    // update video information
                    if ($mediaInfo['State'] === 'success' && isset($mediaInfo['Vid'])) {
                        // start workflow
                        $workflowResult = $byteplusService->startWorkflow($mediaInfo['Vid']);

                        if (!empty($workflowResult)) {
                            $job->update([
                                'status' => VideoJob::STATUS_PROCESSING,
                                'results' => array_merge(
                                    $job->results ?? [],
                                    [
                                        'vid' => $mediaInfo['Vid'],
                                        'workflow_run_id' => $workflowResult['RunId']
                                    ]
                                )
                            ]);

                            $this->info("Workflow Result for job: {$job->job_id}: " . json_encode($workflowResult));
                            Log::info('[ByteplusVideoStatus] Updated Video Job ID: '.$job->id.' - Workflow Result: ', [
                                'workflowResult' => $workflowResult,
                            ]);

                            if (isset($mediaInfo['SourceInfo'])) {
                                $sourceInfo = $mediaInfo['SourceInfo'];
                                $job->update([
                                    'width' => $sourceInfo['Width'] ?? null,
                                    'height' => $sourceInfo['Height'] ?? null,
                                    'duration' => $sourceInfo['Duration'] ?? null,
                                    'bitrate' => $sourceInfo['Bitrate'] ?? null,
                                    'format' => $sourceInfo['Format'] ?? null,
                                ]);
                            }
                        }
                    } elseif ($mediaInfo['State'] === 'fail') {
                        $job->update(['status' => VideoJob::STATUS_FAILED]);
                    }
                }
            });
    }

    /**
     * Check processing videos
     *
     * @param ByteplusService $byteplusService
     * @return void
     */
    private function checkProcessingVideos(ByteplusService $byteplusService): void
    {
        VideoJob::where('status', VideoJob::STATUS_PROCESSING)
            ->whereNotNull('results->workflow_run_id')
            ->chunk(10, function ($jobs) use ($byteplusService) {
                foreach ($jobs as $job) {
                    $this->info("Checking workflow status for job: {$job->job_id}");

                    $runId = $job->results['workflow_run_id'] ?? null;
                    $vid = $job->results['vid'] ?? null;

                    if (!$runId || !$vid) {
                        continue;
                    }

                    $result = $byteplusService->getWorkflowExecution($runId);

                    if (empty($result)) {
                        continue;
                    }

                    // update status based on workflow result
                    if ($result['Status'] === '0') { // Success
                        // first publish the video after succesful processing!
                        $published = $byteplusService->publishVideo($vid);

                        if (!$published) {
                            $this->error("Failed to publish video for job: {$job->job_id}");
                            continue;
                        }

                        $this->info("Successfully published video for job: {$job->job_id}");

                        // get playback URLs for different qualities
                        $playbackLinks = $byteplusService->getPlayInfo($vid);

                        if (!empty($playbackLinks)) {
                            $job->update([
                                'status' => VideoJob::STATUS_COMPLETED,
                                'results' => array_merge(
                                    $job->results ?? [],
                                    [
                                        'workflow_result' => $result,
                                        'playback_links' => $playbackLinks
                                    ]
                                )
                            ]);

                            $this->info("Video processing completed. Playback links generated for job: {$job->job_id}");
                            Log::info('Video processing completed', [
                                'job_id' => $job->job_id,
                                'playback_links' => $playbackLinks
                            ]);
                        } else {
                            Log::error('Failed to get playback links', [
                                'job_id' => $job->job_id,
                                'vid' => $vid
                            ]);
                        }
                    } elseif (is_numeric($result['Status']) && $result['Status'] > 0) { // Error
                        $job->update([
                            'status' => VideoJob::STATUS_FAILED,
                            'results' => array_merge(
                                $job->results ?? [],
                                ['workflow_error' => $result]
                            )
                        ]);

                        $this->error("Video processing failed for job: {$job->job_id}");
                        Log::error('Video processing failed', [
                            'job_id' => $job->job_id,
                            'error' => $result
                        ]);
                    }
                }
            });
    }
}
