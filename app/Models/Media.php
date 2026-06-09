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
        if (! $this->videoJob) {
            return null;
        }

        if ($this->videoJob->status !== VideoJob::STATUS_COMPLETED) {
            return null;
        }

        return $this->videoJob->results['playback_links'] ?? null;
    }

    public function getOriginalUrlAttribute(): string
    {
        return $this->getUrl();
    }

    public function getUrl(string $conversionName = ''): string
    {
        if (storage_use_admin_proxy()) {
            return storage_admin_media_url($this, $conversionName);
        }

        return storage_rewrite_url(parent::getUrl($conversionName)) ?? '';
    }

    public function getFullUrl(string $conversionName = ''): string
    {
        if (storage_use_admin_proxy()) {
            return storage_admin_media_url($this, $conversionName);
        }

        return storage_rewrite_url(parent::getFullUrl($conversionName)) ?? '';
    }
}
