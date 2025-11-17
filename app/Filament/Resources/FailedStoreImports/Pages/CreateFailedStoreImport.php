<?php

namespace App\Filament\Resources\FailedStoreImports\Pages;

use App\Filament\Resources\FailedStoreImports\FailedStoreImportResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateFailedStoreImport extends CreateRecord
{
    protected static string $resource = FailedStoreImportResource::class;
}
