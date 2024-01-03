<?php

namespace App\Models;

use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignQuestionAnswer extends Model implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

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
