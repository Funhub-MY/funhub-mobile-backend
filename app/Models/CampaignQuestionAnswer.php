<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignQuestionAnswer extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'campaigns_questions_answers_users';

    public function question()
    {
        return $this->belongsTo(CampaignQuestion::class, 'campaign_question_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
