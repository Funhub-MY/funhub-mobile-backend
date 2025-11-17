<?php

namespace App\Filament\Resources\CampaignQuestionAnswers\Pages;

use App\Filament\Resources\CampaignQuestionAnswers\CampaignQuestionAnswerResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCampaignQuestionAnswer extends CreateRecord
{
    protected static string $resource = CampaignQuestionAnswerResource::class;
}
