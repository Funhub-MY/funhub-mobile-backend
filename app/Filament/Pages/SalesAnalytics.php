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
