<?php
namespace App\Console\Commands;

use App\Jobs\ByteplusVODProcess;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DispatchByteplusVODProcess extends Command
{
    protected $signature = 'byteplus:process-media {media_ids : Comma-separated list of media IDs to process}';
    protected $description = 'Manually dispatch ByteplusVODProcess for specific media IDs';

    public function handle(): int
    {
        $mediaIds = array_filter(
            array_map('trim', explode(',', $this->argument('media_ids')))
        );

        if (empty($mediaIds)) {
            $this->error('No media IDs provided.');
            return Command::FAILURE;
        }

        $this->info('Processing ' . count($mediaIds) . ' media files...');

        $successCount = 0;
        $failureCount = 0;

        foreach ($mediaIds as $mediaId) {
            $this->line("\nProcessing Media ID: {$mediaId}");

            // Find media
            $media = Media::find($mediaId);

            if (!$media) {
                $this->error("✗ Media ID {$mediaId} not found.");
                $failureCount++;
                continue;
            }

            if (!str_contains($media->mime_type, 'video')) {
                $this->error("✗ Media ID {$mediaId} is not a video file.");
                $failureCount++;
                continue;
            }

            // check if already being processed
            $existingJob = \App\Models\VideoJob::where('media_id', $mediaId)
                ->whereIn('status', [0, 1]) // Pending or Processing
                ->first();

            if ($existingJob) {
                $this->error("✗ Media ID {$mediaId} is already being processed (Job ID: {$existingJob->job_id})");
                $failureCount++;
                continue;
            }

            try {
                ByteplusVODProcess::dispatch($media);
                $this->info("✓ Successfully dispatched job for Media ID {$mediaId}");
                $successCount++;
            } catch (\Exception $e) {
                $this->error("✗ Failed to dispatch job for Media ID {$mediaId}: " . $e->getMessage());
                $failureCount++;
            }
        }

        // summary
        $this->line("\n=== Summary ===");
        $this->info("Successfully dispatched: {$successCount}");
        if ($failureCount > 0) {
            $this->error("Failed to dispatch: {$failureCount}");
        }
        $this->line("Total processed: " . count($mediaIds));

        return $failureCount === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
