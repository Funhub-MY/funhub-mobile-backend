<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SalesReportsExporter;
use Filament\Pages\Page;

class SalesReportExport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.sales-report';

    protected static ?string $navigationGroup = 'Sales';

    protected function getHeaderWidgets(): array
    {
        return [
            SalesReportsExporter::class
        ];
    }

}
