<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAdded;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaListener
{
    public function handle(MediaHasBeenAdded $event)
    {
        $media = $event->media;
        $publicDisks = array_values(config('storage.public_disk_map', []));

        if (str_contains($media->mime_type, 'image') && ! $media->hasCustomProperty('width') && ! $media->hasCustomProperty('height')) {
            try {
                $size = getimagesize(in_array($media->disk, $publicDisks, true)
                    ? $media->getFullUrl()
                    : $media->getPath());

                Media::withoutEvents(function () use ($media, $size) {
                    $media->setCustomProperty('width', $size[0]);
                    $media->setCustomProperty('height', $size[1]);
                    $media->save();
                });
            } catch (\Exception $e) {
                Log::error('Failed to get image size', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
