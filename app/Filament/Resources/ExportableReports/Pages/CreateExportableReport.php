<?php

namespace App\Filament\Resources\ExportableReports\Pages;

use App\Filament\Resources\ExportableReports\ExportableReportResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateExportableReport extends CreateRecord
{
    protected static string $resource = ExportableReportResource::class;
}
