<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAdded;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function handle(MediaHasBeenAdded $event)
    {
        $media = $event->media;
        $path = $media->getPath();

        // if media is image && checkl if media custom properties is without width and height
        if (str_contains($media->mime_type, 'image') && !$media->hasCustomProperty('width') && !$media->hasCustomProperty('height')) {
            try {
                 // getimagesize then save as custom properties withoutevents
                $size = getimagesize($path);
                
                // model without events so wont infinite loop
                Media::withoutEvents(function () use ($media, $size) {
                    $media->setCustomProperty('width', $size[0]);
                    $media->setCustomProperty('height', $size[1]);
                    $media->save();
                });
            } catch (\Exception $e) {
                Log::error('Failed to get image size', [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
