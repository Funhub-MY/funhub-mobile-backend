<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ProductsSales;
use App\Filament\Widgets\RedemptionTrend;
use App\Filament\Widgets\TopContributor;
use App\Filament\Widgets\TransactionAmountByPeriod;
use App\Filament\Widgets\TransactionsOverview;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;

class SalesAnalytics extends Page
{
    use HasPageShield;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.pages.sales-report';

    protected static string | \UnitEnum | null $navigationGroup = 'Sales';

    // widgets
    protected function getHeaderWidgets(): array
    {
        $widgets = [
            TransactionAmountByPeriod::class,
            RedemptionTrend::class,
            ProductsSales::class,
        ];

        return $widgets;
    }
}
