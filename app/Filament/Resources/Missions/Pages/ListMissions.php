<?php

namespace App\Filament\Resources\Missions\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\Missions\MissionResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMissions extends ListRecords
{
    protected static string $resource = MissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
