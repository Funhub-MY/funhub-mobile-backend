<?php
namespace App\Models;
use App\Models\VideoJob;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

class Media extends BaseMedia
{
    public function videoJob()
    {
        return $this->hasOne(VideoJob::class, 'media_id');
    }

    public function getVideoResolutionsAttribute()
    {
        if (!$this->videoJob) {
            return null;
        }

        if ($this->videoJob->status !== VideoJob::STATUS_COMPLETED) {
            return null;
        }

        return $this->videoJob->results['playback_links'] ?? null;
    }
}
