<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ArticleCountChartOverMonths;
use App\Filament\Widgets\ArticleUserEngagementCategory;
use App\Filament\Widgets\Filters;
use App\Filament\Widgets\RedemptionsOverview;
use App\Filament\Widgets\RedemptionsValue;
use App\Filament\Widgets\TopContributor;
use App\Filament\Widgets\UsersChart;
use App\Filament\Widgets\VoucherAvailable;
use Filament\Forms\Components\Section;
use Filament\Pages\Dashboard as BasePage;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Filters\Filter;

class Dashboard extends BasePage
{
    protected function getHeading(): string
    {
        return "Dashboard";
    }

    protected function getColumns(): int | array
    {
        if (auth()->user()->hasRole('merchant')) {
            return 3;
        }
        return 2;
    }

    // widgets
    protected function getWidgets(): array
    {
        $widgets = [
            UsersChart::class,
            ArticleCountChartOverMonths::class,
            TopContributor::class,
            ArticleUserEngagementCategory::class,
        ];

        if (auth()->user()->hasRole('merchant')) {
            // load filers and merchant releated widgets
            $widgets = array_merge($widgets, [
                Filters::class,
                VoucherAvailable::class,
                RedemptionsOverview::class,
                RedemptionsValue::class,
            ]);
        }

        return $widgets;
    }
}
