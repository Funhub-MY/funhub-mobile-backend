<?php

namespace App\Filament\Resources\CampaignQuestionResource\Pages;

use App\Filament\Resources\CampaignQuestionResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCampaignQuestions extends ListRecords
{
    protected static string $resource = CampaignQuestionResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
