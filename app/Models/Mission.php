<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mission extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [
        'id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Morphs to Reward/Reward Component
     */
    public function missionable()
    {
        return $this->morphTo();
    }

    public function participants()
    {
        return $this->belongsToMany(User::class, 'missions_users');
    }
}
