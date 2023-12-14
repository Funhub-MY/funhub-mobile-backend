<?php

namespace App\Filament\Resources\CampaignQuestionResource\Pages;

use App\Filament\Resources\CampaignQuestionResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCampaignQuestion extends EditRecord
{
    protected static string $resource = CampaignQuestionResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }


    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($data['answer_type'] != 'text') {
            // map answers = ['answer' => 1] to jus [1, 2, 3]
            $data['answer'] = json_encode(array_map(function ($answer) {
                return $answer['answer'];
            }, $data['answers']));
        }
        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // if answer_type is not text, then convert answer json to ['answer' => 1]
        if ($data['answer_type'] != 'text') {
            if ($data['answer']) {
                $data['answers'] = array_map(function ($answer) {
                    return ['answer' => $answer];
                }, json_decode($data['answer']));
            }
        }

        return $data;
    }
}
