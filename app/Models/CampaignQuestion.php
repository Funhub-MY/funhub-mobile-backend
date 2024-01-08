<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class CampaignQuestion extends BaseModel implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $guarded = ['id'];

    protected $table = 'campaigns_questions';

    const QUESTION_BANNER = 'question_banners';
    const FOOTER_BANNER = 'footer_banners';

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function usersAnswers()
    {
        return $this->belongsToMany(User::class, 'campaigns_questions_answers_users', 'campaign_question_id', 'user_id');
    }
}
