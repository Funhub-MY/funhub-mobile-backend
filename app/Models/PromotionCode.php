<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Str;
use App\Models\BaseModel;
use App\Models\User;
use App\Models\Reward;
use App\Models\RewardComponent;

class PromotionCode extends BaseModel implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $guarded = ['id'];

    protected $casts = [
        'is_redeemed' => 'boolean',
        'redeemed_at' => 'datetime',
        'tags' => 'array',
    ];

    public function claimedBy()
    {
        return $this->belongsTo(User::class, 'claimed_by_id');
    }

    public function rewardable()
    {
        return $this->morphTo();
    }

    public function reward()
    {
        return $this->morphedByMany(Reward::class, 'rewardable', 'promotion_code_rewardable')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function rewardComponent()
    {
        return $this->morphedByMany(RewardComponent::class, 'rewardable', 'promotion_code_rewardable')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public static function generateUniqueCode(): string
    {
        $attempts = 0;
        $maxAttempts = 5;

        do {
            // generate 4 random characters
            $chars1 = strtoupper(Str::random(4));
            
            // generate 4 random numbers
            $numbers = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            
            // generate 2 random characters
            $chars2 = strtoupper(Str::random(2));
            
            // combine all parts
            $code = $chars1 . $numbers . $chars2;

            $attempts++;

            if ($attempts >= $maxAttempts) {
                throw new \RuntimeException('failed to generate unique code after ' . $maxAttempts . ' attempts');
            }
        } while (static::where('code', $code)->exists());
        
        return $code;
    }
}
