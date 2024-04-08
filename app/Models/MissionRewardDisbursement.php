<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MissionRewardDisbursement extends Model
{
    protected $fillable = ['mission_id', 'user_id', 'reward_quantity'];

    public function mission()
    {
        return $this->belongsTo(Mission::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
