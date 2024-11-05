<?php
namespace App\Jobs;

use App\Models\VideoJob;
use App\Services\ByteplusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ByteplusVODProcess implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly Media $media
    ) {}

    public function handle(ByteplusService $byteplusService): void
    {
        // create video job record
        $videoJob = VideoJob::create([
            'media_id' => $this->media->id,
            'provider' => 'byteplus',
            'status' => 0,
            'title' => $this->media->name,
            'source_url' => $this->media->getUrl(),
        ]);

        $uploadResult = $byteplusService->uploadMediaByUrl(
            $this->media->getUrl(),
            $this->media->name
        );

        if (empty($uploadResult)) {
            $videoJob->update(['status' => 3]); // Failed
            return;
        }

        // update job ID
        $videoJob->update([
            'job_id' => $uploadResult['JobId'],
            'status' => 1, // Processing
        ]);

        // start workflow
        // workflow to process different qualities of video
        if (isset($uploadResult['Vid'])) {
            $workflowResult = $byteplusService->startWorkflow($uploadResult['Vid']);

            if (!empty($workflowResult)) {
                $videoJob->update([
                    'status' => 1,
                    'results' => ['workflow_run_id' => $workflowResult['RunId']]
                ]);
            }
        }
    }
}
