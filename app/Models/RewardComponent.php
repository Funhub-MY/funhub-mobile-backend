<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\HasMedia;

class RewardComponent extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    const COLLECTION_NAME = 'reward_components';

    protected $appends = [
        'thumbnail_url'
    ];

    protected $guarded = [
        'id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rewards()
    {
        return $this->belongsToMany(Reward::class, 'rewards_reward_components', 'reward_component_id', 'reward_id')
            ->withPivot('points')
            ->withTimestamps();
    }
    
    public function getThumbnailUrlAttribute()
    {
        return $this->getFirstMediaUrl(static::COLLECTION_NAME);
    }
}
