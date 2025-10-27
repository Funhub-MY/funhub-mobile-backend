<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserMission extends Model
{
    use HasFactory;

    const MISSION_ONE = 1;
    const MISSION_TWO = 2;
    const MISSION_THREE = 3;
    const MISSION_FOUR = 4;
    const MISSION_FIVE = 5;
    const MISSION_SIX = 6;

    protected $table = 'user_missions';

    protected $fillable = [
        'user_id',
        'mission_1',
        'mission_2',
        'mission_3',
        'mission_4',
        'mission_5',
        'mission_6',
        'cycle',
        'draw_chance',
        'extra_chance',
        'total_drawn',
    ];
}
