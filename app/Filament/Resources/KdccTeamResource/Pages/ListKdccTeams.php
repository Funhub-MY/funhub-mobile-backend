<?php

namespace App\Filament\Resources\KdccTeamResource\Pages;

use App\Filament\Resources\KdccTeamResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKdccTeams extends ListRecords
{
    protected static string $resource = KdccTeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
