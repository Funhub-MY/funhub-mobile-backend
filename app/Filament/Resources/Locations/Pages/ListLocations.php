<?php

namespace App\Filament\Resources\Locations\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\Locations\LocationResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLocations extends ListRecords
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
