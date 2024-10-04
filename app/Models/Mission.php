<?php

namespace App\Models;

use App\Models\BaseModel;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\HasMedia;

class Mission extends BaseModel implements HasMedia, Auditable
{
    use HasFactory, SoftDeletes, InteractsWithMedia, \OwenIt\Auditing\Auditable;

    const MEDIA_COLLECTION_NAME = 'mission_gallery';
    const COMPLETED_MISSION_COLLECTION_EN = 'completed_mission_en';
    const COMPLETED_MISSION_COLLECTION_ZH = 'completed_mission_zh';

    protected $guarded = [
        'id'
    ];

    protected $casts = [
        'events' => 'json',
        'values' => 'json',
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
            ->withPivot('id', 'is_completed', 'claimed_at', 'last_rewarded_at', 'started_at', 'current_values', 'completed_at')
            ->withTimestamps();
    }

    // scope enabled
    public function scopeEnabled($query)
    {
        return $query->where('status', 1);
    }
}
