<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignQuestion extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'campaigns_questions';

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function usersAnswers()
    {
        return $this->belongsToMany(User::class, 'campaigns_questions_answers_users', 'campaign_question_id', 'user_id');
    }
}
