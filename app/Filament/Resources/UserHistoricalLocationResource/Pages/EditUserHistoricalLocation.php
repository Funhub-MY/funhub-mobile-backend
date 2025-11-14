<?php

namespace App\Filament\Resources\UserHistoricalLocationResource\Pages;

use App\Filament\Resources\UserHistoricalLocationResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserHistoricalLocation extends EditRecord
{
    protected static string $resource = UserHistoricalLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
