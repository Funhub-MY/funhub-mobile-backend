<?php

namespace App\Filament\Resources\FailedStoreImportResource\Pages;

use App\Filament\Resources\FailedStoreImportResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFailedStoreImports extends ListRecords
{
    protected static string $resource = FailedStoreImportResource::class;

    protected function getActions(): array
    {
        return [];
    }
}
