<?php

namespace App\Filament\Resources\CampaignQuestionAnswers\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\CampaignQuestionAnswers\CampaignQuestionAnswerResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCampaignQuestionAnswers extends ListRecords
{
    protected static string $resource = CampaignQuestionAnswerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
