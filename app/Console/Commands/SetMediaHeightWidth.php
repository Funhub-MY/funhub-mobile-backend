<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SetMediaHeightWidth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:fix-width-height';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Media Fix Width Height';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // get all media without custom_properties of width and height
        $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::whereNull('custom_properties->width')
            ->whereNull('custom_properties->height')->get();

        foreach($media as $m) {
            // ensure media is image and has no custom_properties width and height
            if (str_contains($m->mime_type, 'image') && !$m->hasCustomProperty('width') && !$m->hasCustomProperty('height')) {
                try {
                    // set custom_properties width and height
                    $imageSize = getimagesize($m->getPath());
    
                    // save the media width and height
                    Media::withoutEvents(function () use ($m, $imageSize) {
                        $m->setCustomProperty('width', $imageSize[0]);
                        $m->setCustomProperty('height', $imageSize[1]);
                        $m->save();
    
                        $this->info('Media with width and height SAVED for ID: '.$m->id.' PATH: '.$m->getPath());
                    });
                } catch (\Exception $ex) {
                    $this->error('Media with width and height FAILED for ID: '.$m->id.' PATH: '.$m->getPath());
                }
            }
        }
    }
}
