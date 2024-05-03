<?php

namespace App\Filament\Resources\StoreResource\Pages;

use App\Filament\Resources\StoreResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateStore extends CreateRecord
{
    protected static string $resource = StoreResource::class;


    protected function mutateFormDataBeforeCreate(array $data): array
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
