<?php
 
namespace App\Filament\Pages;

use App\Filament\Widgets\ArticleCountChartOverMonths;
use App\Filament\Widgets\ArticleUserEngagementCategory;
use App\Filament\Widgets\TopContributor;
use App\Filament\Widgets\UsersChart;
use Filament\Pages\Dashboard as BasePage;
 
class Dashboard extends BasePage
{ 
    protected function getHeading(): string
    {
        return "Dashboard";
    }

    // widgets
    protected function getWidgets(): array
    {
        return [
            UsersChart::class,
            ArticleCountChartOverMonths::class,
            TopContributor::class,
            ArticleUserEngagementCategory::class,
        ];
    }
}
