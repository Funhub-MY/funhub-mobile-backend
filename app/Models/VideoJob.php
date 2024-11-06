<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class VideoJob extends Model
{
    use HasFactory;

    const STATUS = [
        0 => 'Uploading',
        1 => 'Processing',
        2 => 'Completed',
        3 => 'Failed'
    ];

    const STATUS_UPLOADING = 0;
    const STATUS_PROCESSING = 1;
    const STATUS_COMPLETED = 2;
    const STATUS_FAILED = 3;

    protected $table = 'video_jobs';

    protected $guarded = ['id'];

    protected $casts = [
        'results' => 'array',
    ];

    /**
     * Media relationship
     *
     * @return BelongsTo
     */
    public function media() : BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
}
