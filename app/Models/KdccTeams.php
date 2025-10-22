<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KdccTeams extends Model
{
    use HasFactory;

    protected $table = 'kdcc_teams';

    protected $fillable = [
        'name',
        'category_id',
        'vote_count',
        'team_image_path'
    ];

    protected $casts = [
        'category_id' => 'integer',
        'vote_count' => 'integer',
    ];

    /**
     * Get all votes for this team
     */
    public function votes()
    {
        return $this->hasMany(KdccVote::class, 'team_id');
    }

    /**
     * Check if a specific user has voted for this team
     */
    public function hasVotedBy($userId)
    {
        return $this->votes()->where('user_id', $userId)->exists();
    }

    /**
     * Get the team's image URL
     */
    public function getImageUrlAttribute()
    {
        return $this->team_image_path 
            ? asset('/images/kdcc/' . $this->team_image_path)
            : asset('/images/kdcc/default.jpeg');
    }
}
