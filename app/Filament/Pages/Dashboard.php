<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ArticleCountChartOverMonths;
use App\Filament\Widgets\ArticleUserEngagementCategory;
use App\Filament\Widgets\Filters;
use App\Filament\Widgets\RedemptionsOverview;
use App\Filament\Widgets\RedemptionsValue;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TopContributor;
use App\Filament\Widgets\UsersChart;
use App\Filament\Widgets\VoucherAvailable;
use Filament\Forms\Components\Section;
use Filament\Pages\Dashboard as BasePage;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Filters\Filter;
use Filament\Widgets\FilamentInfoWidget;

class Dashboard extends BasePage
{
    public function getHeading(): string
    {
        return "Dashboard";
    }

    public function getColumns(): int | array
    {
        if (auth()->user()->hasRole('merchant')) {
            return 3;
        }
        return 2;
    }

    // Override getWidgets to return empty array - this prevents Dashboard from showing discovered widgets
    public function getWidgets(): array
    {
        return [];
    }
    
    // Override getVisibleWidgets to ensure no widgets are displayed
    public function getVisibleWidgets(): array
    {
        // Force return empty array - this is what the dashboard view uses
        return [];
    }
    
    // Override getWidgetData to prevent any widget data from being passed
    public function getWidgetData(): array
    {
        return [];
    }
    
    // Override filterVisibleWidgets to ensure nothing gets through
    protected function filterVisibleWidgets(array $widgets): array
    {
        return [];
    }
    
    // Also override header and footer widgets just in case
    public function getHeaderWidgets(): array
    {
        return [];
    }
    
    public function getFooterWidgets(): array
    {
        return [];
    }
}
