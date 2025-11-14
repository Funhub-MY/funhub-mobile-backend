<?php

namespace App\Filament\Resources\FailedStoreImportResource\Pages;

use App\Filament\Resources\FailedStoreImportResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFailedStoreImport extends EditRecord
{
    protected static string $resource = FailedStoreImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
