<?php

namespace App\Filament\Resources\ExportableReportResource\Pages;

use App\Filament\Resources\ExportableReportResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExportableReport extends EditRecord
{
    protected static string $resource = ExportableReportResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
