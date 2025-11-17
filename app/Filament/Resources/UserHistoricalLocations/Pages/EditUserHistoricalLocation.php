<?php

namespace App\Filament\Resources\UserHistoricalLocations\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\UserHistoricalLocations\UserHistoricalLocationResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserHistoricalLocation extends EditRecord
{
    protected static string $resource = UserHistoricalLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
