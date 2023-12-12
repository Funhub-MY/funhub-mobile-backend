<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignQuestion extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function answers()
    {
        return $this->belongsToMany(CampaignQuestionAnswer::class, 'campaigns_questions_answers_users', 'campaign_question_id', 'answer_id');
    }
}
