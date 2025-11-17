<?php

namespace App\Filament\Resources\ExportableReports\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\ExportableReports\ExportableReportResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExportableReport extends EditRecord
{
    protected static string $resource = ExportableReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
