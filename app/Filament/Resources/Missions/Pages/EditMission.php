<?php

namespace App\Filament\Resources\Missions\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\Missions\MissionResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMission extends EditRecord
{
    protected static string $resource = MissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // mutate $data['events'] $data['values'] to $data['events_values']

        $events = $data['events'];
        $values = $data['values'];

        $data['events_values'] = array_map(function($event, $value) {
            return [
                'event' => $event,
                'value' => $value
            ];
        }, $events, $values);
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
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
