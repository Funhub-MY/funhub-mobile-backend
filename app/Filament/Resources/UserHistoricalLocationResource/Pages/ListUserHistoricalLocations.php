<?php

namespace App\Filament\Resources\UserHistoricalLocationResource\Pages;

use App\Filament\Resources\UserHistoricalLocationResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserHistoricalLocations extends ListRecords
{
    protected static string $resource = UserHistoricalLocationResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
