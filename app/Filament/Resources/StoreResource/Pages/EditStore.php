<?php

namespace App\Filament\Resources\StoreResource\Pages;

use App\Filament\Resources\StoreResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStore extends EditRecord
{
    protected static string $resource = StoreResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // inverse business_hours if there is json data
        if ($data['business_hours']) {
            $data['business_hours'] = collect(json_decode($data['business_hours'], true))->map(function ($item, $key) {
                return [
                    'day' => $key,
                    'open_time' => $item['open_time'],
                    'close_time' => $item['close_time']
                ];
            })->values()->toArray();
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (count($data['business_hours']) > 0) {
            $data['business_hours'] = json_encode(collect($data['business_hours'])->mapWithKeys(function ($item) {
                return [$item['day'] => [
                    'open_time' => $item['open_time'],
                    'close_time' => $item['close_time']
                ]];
            })->toArray());
        }

        return $data;
    }
}
