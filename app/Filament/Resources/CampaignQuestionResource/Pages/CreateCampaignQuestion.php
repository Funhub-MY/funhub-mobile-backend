<?php

namespace App\Filament\Resources\CampaignQuestionResource\Pages;

use App\Filament\Resources\CampaignQuestionResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCampaignQuestion extends CreateRecord
{
    protected static string $resource = CampaignQuestionResource::class;


    protected function mutateFormDataBeforeCreate(array $data): array
    {
        dd($data);
        if ($data['answer_type'] != 'text') {
            $data['answer'] = json_encode($data['answers']);
        }

        return $data;
    }
}
