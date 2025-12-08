<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserBadge extends Pivot
{
    use HasFactory;

    protected $table = 'user_badges';

    public $incrementing = true;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'metadata' => 'array',
        'earned_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * The user who earned the badge
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The badge that was earned
     */
    public function badge()
    {
        return $this->belongsTo(Badge::class);
    }

    /**
     * The reservation that triggered this badge (if any)
     */
    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * Set this badge as the user's showcase badge
     * Ensures only one badge is active per user
     */
    public function setAsShowcase(): void
    {
        // Deactivate all other badges for this user
        self::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_active' => false]);

        // Activate this badge
        $this->update(['is_active' => true]);
    }

    /**
     * Scope active showcase badges
     */
    public function scopeShowcase($query)
    {
        return $query->where('is_active', true);
    }
}

