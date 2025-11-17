<?php

namespace App\Filament\Resources\FailedStoreImports\Pages;

use App\Filament\Resources\FailedStoreImports\FailedStoreImportResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFailedStoreImports extends ListRecords
{
    protected static string $resource = FailedStoreImportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
