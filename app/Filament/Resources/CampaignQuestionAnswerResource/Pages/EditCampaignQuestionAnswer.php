<?php

namespace App\Filament\Resources\CampaignQuestionAnswerResource\Pages;

use App\Filament\Resources\CampaignQuestionAnswerResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCampaignQuestionAnswer extends EditRecord
{
    protected static string $resource = CampaignQuestionAnswerResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
