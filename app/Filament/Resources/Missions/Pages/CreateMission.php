<?php

namespace App\Filament\Resources\Missions\Pages;

use App\Filament\Resources\Missions\MissionResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMission extends CreateRecord
{
    protected static string $resource = MissionResource::class;


    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // modify
        // "events_values" => array:2 [▼
        //     0 => array:2 [▼
        //     "event" => "article_created"
        //     "value" => "2"
        //     ]
        //     1 => array:2 [▼
        //     "event" => "like_comment"
        //     "value" => "2"
        //     ]
        // ]
        $data['events'] = array_map(function ($event) {
            return $event['event'];
        }, $data['events_values']);

        $data['values'] = array_map(function ($event) {
            return $event['value'];
        }, $data['events_values']);

        unset($data['events_values']);

        return $data;
    }
}
