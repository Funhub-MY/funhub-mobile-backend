<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\HasMedia;

class Mission extends BaseModel implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    const MEDIA_COLLECTION_NAME = 'mission_gallery';

    protected $guarded = [
        'id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Morphs to Reward/Reward Component
     */
    public function missionable()
    {
        return $this->morphTo();
    }

    public function participants()
    {
        return $this->belongsToMany(User::class, 'missions_users')
            ->withPivot('is_completed', 'started_at', 'current_value', 'completed_at')
            ->withTimestamps();
    }

    // scope enabled
    public function scopeEnabled($query)
    {
        return $query->where('status', 1);
    }
}
