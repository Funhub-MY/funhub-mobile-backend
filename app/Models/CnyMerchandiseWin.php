<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CnyMerchandiseWin extends Model
{
    use HasFactory;

    protected $table = 'cny_merchandise_wins';

    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cnyMerchandise()
    {
        return $this->belongsTo(CnyMerchandise::class, 'cny_merchandise_id');
    }

    public function cnyLuckyDraw()
    {
        return $this->belongsTo(CnyLuckyDraw::class, 'cny_lucky_draw_id');
    }
}
