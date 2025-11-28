<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;

class Badge extends BaseModel implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Campaign this badge belongs to
     */
    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Users who have earned this badge
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_badges')
            ->withPivot(['earned_at', 'reservation_id', 'progress_value', 'metadata', 'is_active']);
    }

    /**
     * User badges (direct relationship to pivot)
     */
    public function userBadges()
    {
        return $this->hasMany(UserBadge::class);
    }

    /**
     * Scope active badges
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope badges for a campaign
     */
    public function scopeForCampaign($query, $campaignId)
    {
        return $query->where('campaign_id', $campaignId);
    }

    /**
     * Get badge count awarded
     */
    public function getEarnedCountAttribute(): int
    {
        return $this->userBadges()->count();
    }
}
