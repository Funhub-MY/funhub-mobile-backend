<?php

namespace App\Filament\Resources\ExportableReports\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\ExportableReports\ExportableReportResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExportableReports extends ListRecords
{
    protected static string $resource = ExportableReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
