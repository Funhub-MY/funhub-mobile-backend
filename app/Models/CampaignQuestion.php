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

    public function answers()
    {
        return $this->hasMany(CampaignQuestionAnswer::class, 'campaign_question_id');
    }
}
