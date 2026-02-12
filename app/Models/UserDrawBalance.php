<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Current project draw balance: product-purchase extra chances + mission draw chance + total drawn.
 * Uses table user_draw_balance so the current project does not depend on user_missions (which v2 may drop).
 */
class UserDrawBalance extends Model
{
    use HasFactory;

    const MISSION_ONE = 1;
    const MISSION_TWO = 2;
    const MISSION_THREE = 3;
    const MISSION_FOUR = 4;

    protected $table = 'user_draw_balance';

    protected $fillable = [
        'user_id',
        'mission_1',
        'mission_2',
        'mission_3',
        'mission_4',
        'cycle',
        'draw_chance',
        'extra_chance',
        'total_drawn',
    ];

    protected $attributes = [
        'mission_1' => 0,
        'mission_2' => 0,
        'mission_3' => 0,
        'mission_4' => 0,
        'cycle' => 0,
        'draw_chance' => 0,
        'extra_chance' => 0,
        'total_drawn' => 0,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
