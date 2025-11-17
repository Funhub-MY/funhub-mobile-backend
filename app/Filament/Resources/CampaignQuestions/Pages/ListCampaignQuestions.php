<?php

namespace App\Filament\Resources\CampaignQuestions\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\CampaignQuestions\CampaignQuestionResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCampaignQuestions extends ListRecords
{
    protected static string $resource = CampaignQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
