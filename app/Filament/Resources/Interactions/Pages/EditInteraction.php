<?php

namespace App\Filament\Resources\Interactions\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\Interactions\InteractionResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInteraction extends EditRecord
{
    protected static string $resource = InteractionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
