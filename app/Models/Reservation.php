<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Reservation extends BaseModel implements HasMedia, Auditable
{
    use HasFactory, InteractsWithMedia, \OwenIt\Auditing\Auditable;

    protected $guarded = ['id'];

    protected $casts = [
        'form_data' => 'array',
        'reservation_date' => 'datetime',
        'approved_at' => 'datetime',
    ];

    const MEDIA_COLLECTION_FORM_FILES = 'reservation_form_files';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get form file by field key
     */
    public function getFormFile($fieldKey)
    {
        return $this->getFirstMedia(self::MEDIA_COLLECTION_FORM_FILES, function ($media) use ($fieldKey) {
            return $media->getCustomProperty('field_key') === $fieldKey;
        });
    }

    /**
     * Get all form files
     */
    public function getFormFiles()
    {
        return $this->getMedia(self::MEDIA_COLLECTION_FORM_FILES);
    }
}

