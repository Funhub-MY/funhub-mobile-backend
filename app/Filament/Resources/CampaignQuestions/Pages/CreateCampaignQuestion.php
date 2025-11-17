<?php

namespace App\Filament\Resources\CampaignQuestions\Pages;

use App\Filament\Resources\CampaignQuestions\CampaignQuestionResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCampaignQuestion extends CreateRecord
{
    protected static string $resource = CampaignQuestionResource::class;


    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($data['answer_type'] != 'text') {
            // map answers = ['answer' => 1] to jus [1, 2, 3]
            $data['answer'] = json_encode(array_map(function ($answer) {
                return $answer['answer'];
            }, $data['answers']));
        }
        return $data;
    }
}
