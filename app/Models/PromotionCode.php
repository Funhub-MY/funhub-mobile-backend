<?php

namespace App\Models;

use RuntimeException;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Str;
use App\Models\BaseModel;
use App\Models\Reward;
use App\Models\RewardComponent;
use App\Models\PromotionCodeGroup;
use App\Models\Transaction;

class PromotionCode extends BaseModel implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $guarded = ['id'];

    protected $casts = [
        'is_redeemed' => 'boolean',
        'redeemed_at' => 'datetime',
        'tags' => 'array',
        'status' => 'boolean',
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

    public function promotionCodeGroup()
    {
        return $this->belongsTo(PromotionCodeGroup::class);
    }

    public function isActive(): bool
    {
        return $this->status && ($this->promotionCodeGroup === null || $this->promotionCodeGroup->isActive());
    }
    
    public function transactions()
    {
        return $this->belongsToMany(Transaction::class, 'promotion_code_transaction')
            ->withTimestamps();
    }

	public function users()
	{
		return $this->belongsToMany(User::class, 'promotion_code_user')
			->withPivot('usage_count', 'last_used_at')
			->withTimestamps();
	}

    public static function generateUniqueCode(): string
    {
        $attempts = 0;
        $maxAttempts = 5;

        do {
            // generate 4 random characters
            $chars1 = strtoupper(Str::random(2));
            
            // generate 4 random numbers
            $numbers = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            
            // generate 2 random characters
            $chars2 = strtoupper(Str::random(2));
            
            // combine all parts
            $code = $chars1 . $numbers . $chars2;

            $attempts++;

            if ($attempts >= $maxAttempts) {
                throw new RuntimeException('failed to generate unique code after ' . $maxAttempts . ' attempts');
            }
        } while (static::where('code', $code)->exists());
        
        return $code;
    }
}
