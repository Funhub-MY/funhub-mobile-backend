<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CnyLuckyDraw extends Model
{
    use HasFactory;

    protected $table = 'cny_lucky_draws';

    protected $guarded = ['id'];

    protected $casts = [
        'drawn_at' => 'datetime',
    ];

    public const REWARD_TYPE_PROMO_CODE = 'promo_code';
    public const REWARD_TYPE_MERCHANDISE = 'merchandise';
    public const REWARD_TYPE_NONE = 'nothing';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function promotionCode()
    {
        return $this->belongsTo(PromotionCode::class, 'promotion_code_id');
    }

    public function cnyMerchandise()
    {
        return $this->belongsTo(CnyMerchandise::class, 'cny_merchandise_id');
    }

    public function merchandiseWin()
    {
        return $this->hasOne(CnyMerchandiseWin::class, 'cny_lucky_draw_id');
    }
}
