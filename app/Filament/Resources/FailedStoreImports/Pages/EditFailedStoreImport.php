<?php

namespace App\Filament\Resources\FailedStoreImports\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\FailedStoreImports\FailedStoreImportResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFailedStoreImport extends EditRecord
{
    protected static string $resource = FailedStoreImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
