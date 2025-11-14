<?php

namespace App\Filament\Resources\ExportableReportResource\Pages;

use App\Filament\Resources\ExportableReportResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExportableReports extends ListRecords
{
    protected static string $resource = ExportableReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
