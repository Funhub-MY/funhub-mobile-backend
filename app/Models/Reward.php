<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Reward extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    const COLLECTION_NAME = 'rewards';

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

    public function rewardComponents()
    {
        return $this->belongsToMany(RewardComponent::class, 'rewards_reward_components')
            ->withPivot('points') // points required to form a reward
            ->withTimestamps();
    }

    public function getThumbnailUrlAttribute()
    {
        return $this->getFirstMediaUrl(static::COLLECTION_NAME);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_reward')->withPivot('quantity');
    }
    
}
