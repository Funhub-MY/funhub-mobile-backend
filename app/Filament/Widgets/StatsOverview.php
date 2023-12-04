<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use App\Models\User;
use App\Models\View;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use NumberFormatter;

class StatsOverview extends BaseWidget
{
    use HasWidgetShield;

    protected function getUserTotal() {
        $currentTotal = User::count();
        // last month total
        $upToLastMonthTotal = User::where('created_at', '<', now()->subMonth())->count();

        $changesSinceLastMonthPercent = (($currentTotal - $upToLastMonthTotal) / $currentTotal) * 100;
        return [
            'total' => User::count(),
            'changes_compared_last_month' => $changesSinceLastMonthPercent,
            'increased' => $changesSinceLastMonthPercent > 0,
        ];
    }

    protected function getActiveUserTotal(){
        // get active user by view activity from last month
        $currentTotal = View::where('created_at', '>', now()->startOfMonth())->distinct('user_id')->count('user_id');

        // get active user by view activity from last 1 months
        $upToLastMonthTotal = View::where('created_at', '>', now()->subMonth()->startOfMonth())->distinct('user_id')->count('user_id');

        $changesSinceLastMonthPercent = 0;
        if ($currentTotal != 0) {
            $changesSinceLastMonthPercent = (($currentTotal - $upToLastMonthTotal) / $currentTotal) * 100;
        }
        return [
            'total' => $currentTotal,
            'changes_compared_last_month' => $changesSinceLastMonthPercent,
            'increased' => $changesSinceLastMonthPercent > 0,
        ];
    }

    protected function getArticlesPublished() {
        $currentTotal = Article::published()->count();
        // last month total
        $upToLastMonthTotal = Article::published()->where('created_at', '<', now()->subMonth())->count();

        $changesSinceLastMonthPercent = (($currentTotal - $upToLastMonthTotal) / $currentTotal) * 100;
        return [
            'total' => Article::published()->count(),
            'changes_compared_last_month' => $changesSinceLastMonthPercent,
            'increased' => $changesSinceLastMonthPercent > 0,
        ];
    }


    protected function getCards(): array
    {
        $humanReadable = new NumberFormatter(locale_get_default(), NumberFormatter::PADDING_POSITION);
        $userData = $this->getUserTotal();
        $activeUserData = $this->getActiveUserTotal();

        $articleData = $this->getArticlesPublished();

        return [
            Card::make('Total Users', $humanReadable->format($userData['total']))
                ->description($this->getChangeMessage($userData['changes_compared_last_month']))
                ->descriptionIcon($this->getChangeIcon($userData['changes_compared_last_month']))
                ->color($this->getChangeColor($userData['changes_compared_last_month'])),

            Card::make('Active Users', $humanReadable->format($activeUserData['total']))
                ->description($this->getChangeMessage($activeUserData['changes_compared_last_month']))
                ->descriptionIcon($this->getChangeIcon($activeUserData['changes_compared_last_month']))
                ->color($this->getChangeColor($activeUserData['changes_compared_last_month'])),

            Card::make('Total Published Articles', $humanReadable->format($articleData['total']))
                ->description($this->getChangeMessage($articleData['changes_compared_last_month']))
                ->descriptionIcon($this->getChangeIcon($articleData['changes_compared_last_month']))
                ->color($this->getChangeColor($articleData['changes_compared_last_month'])),

        ];
    }

    protected function getChangeMessage($value) {
        $humanReadable = new NumberFormatter( locale_get_default(), NumberFormatter::PADDING_POSITION);

        if ($value > 0) {
            return $humanReadable->format(abs($value)). '% increased from last month';
        } else if ($value < 0) {
            return $humanReadable->format(abs($value)). '% decreased from last month';
        } else {
            return 'No change from last month';
        }
    }

    protected function getChangeColor($value) {
        if ($value > 0) {
            return 'success';
        } else if ($value < 0) {
            return 'danger';
        } else {
            return 'gray';
        }
    }

    protected function getChangeIcon($value) {
        if ($value > 0) {
            return 'heroicon-s-trending-up';
        } else if ($value < 0) {
            return 'heroicon-s-trending-down';
        } else {
            return '';
        }
    }
}
