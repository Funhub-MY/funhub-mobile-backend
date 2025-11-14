<?php

namespace App\Filament\Resources\CampaignQuestionAnswerResource\Pages;

use App\Filament\Resources\CampaignQuestionAnswerResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCampaignQuestionAnswers extends ListRecords
{
    protected static string $resource = CampaignQuestionAnswerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
