<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SalesReportsExporter;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;

class SalesReportExport extends Page
{
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.sales-report';

    protected static ?string $navigationGroup = 'Sales';

    // dont register menu if role is merchant
    public static function registerNavigationItems(): void
    {
        if (! static::shouldRegisterNavigation()) {
            return;
        }

        if (auth()->user()->hasRole('merchant')) {
            return;
        }
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // SalesReportsExporter::class
        ];
    }

}
