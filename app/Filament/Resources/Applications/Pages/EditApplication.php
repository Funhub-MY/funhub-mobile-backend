<?php

namespace App\Filament\Resources\Applications\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\Applications\ApplicationResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditApplication extends EditRecord
{
    protected static string $resource = ApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
