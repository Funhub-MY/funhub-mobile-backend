<?php

namespace App\Filament\Resources\Maintenances\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\Maintenances\MaintenanceResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaintenance extends EditRecord
{
    protected static string $resource = MaintenanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
