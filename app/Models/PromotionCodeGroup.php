<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class PromotionCodeGroup extends Model implements Auditable
{
    use HasFactory, SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => 'boolean',
        'campaign_from' => 'datetime',
        'campaign_until' => 'datetime',
    ];

    public function rewardable()
    {
        return $this->morphTo();
    }

    public function promotionCodes()
    {
        return $this->hasMany(PromotionCode::class);
    }

    public function reward()
    {
        return $this->morphedByMany(Reward::class, 'rewardable', 'promotion_code_group_rewardable')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function rewardComponent()
    {
        return $this->morphedByMany(RewardComponent::class, 'rewardable', 'promotion_code_group_rewardable')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function isActive(): bool
    {
        $now = now();
        return $this->status &&
            ($this->campaign_from === null || $now->gte($this->campaign_from)) &&
            ($this->campaign_until === null || $now->lte($this->campaign_until));
    }
}
