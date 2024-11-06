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
        1 => 'Uploading',
        2 => 'Processing',
        3 => 'Completed',
        4 => 'Failed'
    ];

    const STATUS_UPLOADING = 1;
    const STATUS_PROCESSING = 2;
    const STATUS_COMPLETED = 3;
    const STATUS_FAILED = 4;

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
