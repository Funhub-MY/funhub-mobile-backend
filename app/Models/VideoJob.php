<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class VideoJob extends Model
{
    use HasFactory;

    protected $table = 'video_jobs';

    protected $guarded = ['id'];

    public function media()
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
}
