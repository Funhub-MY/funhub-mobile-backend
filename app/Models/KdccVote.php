<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KdccVote extends Model
{
    use HasFactory;

    protected $table = 'kdcc_votes';

    protected $fillable = [
        'user_id',
        'category_id',
        'team_id'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'category_id' => 'integer',
        'team_id' => 'integer',
    ];

    /**
     * Get the user who cast this vote
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the team that was voted for
     */
    public function team()
    {
        return $this->belongsTo(KdccTeams::class, 'team_id');
    }
}
