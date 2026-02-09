<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CnyFortunePick extends Model
{
    use HasFactory;

    protected $table = 'cny_fortune_picks';

    protected $guarded = ['id'];

    protected $casts = [
        'picked_at' => 'datetime',
    ];

    public const FORTUNE_REWARD_NOTHING = 'nothing';
    public const FORTUNE_REWARD_PROMO_49 = 'promo_49';
    public const FORTUNE_REWARD_PROMO_50 = 'promo_50';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function promotionCode()
    {
        return $this->belongsTo(PromotionCode::class, 'promotion_code_id');
    }
}
