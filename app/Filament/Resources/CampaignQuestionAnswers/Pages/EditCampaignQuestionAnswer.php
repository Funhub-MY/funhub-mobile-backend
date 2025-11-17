<?php

namespace App\Filament\Resources\CampaignQuestionAnswers\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\CampaignQuestionAnswers\CampaignQuestionAnswerResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCampaignQuestionAnswer extends EditRecord
{
    protected static string $resource = CampaignQuestionAnswerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
