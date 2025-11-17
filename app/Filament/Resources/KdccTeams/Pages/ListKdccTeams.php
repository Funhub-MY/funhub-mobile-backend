<?php

namespace App\Filament\Resources\KdccTeams\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\KdccTeams\KdccTeamResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKdccTeams extends ListRecords
{
    protected static string $resource = KdccTeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
