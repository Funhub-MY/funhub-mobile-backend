<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Zebra_Image;

class CreateImageVariants implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $media, $disk;

    public function __construct(Media $media, $disk = 's3')
    {
        $this->media = $media;
        $this->disk = $disk;
    }

    public function handle()
    {
        // try {
            Log::info('Creating image variants', [
                'media_id' => $this->media->id,
            ]);
            $image = new Zebra_Image();
            $image->source_path = $this->media->getPath();

            Log::info('Creating image variants', [
                'media_id' => $this->media->id,
                'source_path' => $image->source_path,
            ]);

            // get the original image filename and extension
            $filename = pathinfo($this->media->file_name, PATHINFO_FILENAME);
            $extension = pathinfo($this->media->file_name, PATHINFO_EXTENSION);

            $s3Directory = explode('/', $this->media->getPath())[0];

            // check if storage/app/conversions folder exists, if not create it first
            if (!is_dir(storage_path('app/conversions'))) {
                mkdir(storage_path('app/conversions'));
            }

            // create medium variant (50% of original width x height)
            $mediumWidth = round($this->media->getCustomProperty('width') * 0.5);
            $mediumHeight = round($this->media->getCustomProperty('height') * 0.5);
            $mediumFilename = $filename . '-m.' . $extension;

            $image->target_path = storage_path('app/conversions/' . $mediumFilename);
            $image->resize($mediumWidth, $mediumHeight, ZEBRA_IMAGE_CROP_CENTER);

            Log::info('Creating image variants', [
                'media_id' => $this->media->id,
                'target_path' => $image->target_path,
                'has_target_path' => file_exists($image->target_path) ? 'true' : 'false',
            ]);

            // upload the medium variant to S3
            Storage::disk($this->disk)->put(
                $s3Directory . '/' . $mediumFilename,
                file_get_contents($image->target_path)
            );

            // // create small variant (25% of original width x height)
            // $smallWidth = round($this->media->getCustomProperty('width') * 0.25);
            // $smallHeight = round($this->media->getCustomProperty('height') * 0.25);
            // $smallFilename = $filename . '-s.' . $extension;
            // $image->target_path = storage_path('app/conversions/' . $smallFilename);
            // $image->resize($smallWidth, $smallHeight, ZEBRA_IMAGE_CROP_CENTER);

            // Storage::disk($this->disk)->put(
            //     $s3Directory . '/' . $smallFilename,
            //     file_get_contents($image->target_path)
            // );
        // } catch (\Exception $e) {
        //     // Log the error
        //     Log::error('Failed to create image variants', [
        //         'media_id' => $this->media->id,
        //         'error' => $e->getMessage(),
        //     ]);
        // }
    }
}
