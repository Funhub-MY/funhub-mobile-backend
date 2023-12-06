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
        $currentTotal = User::selectRaw('COUNT(*) as total')->value('total');
        // last month total
        $upToLastMonthTotal = User::where('created_at', '<', now()->subMonth())->selectRaw('COUNT(*) as total')->value('total');

        $changesSinceLastMonthPercent = (($currentTotal - $upToLastMonthTotal) / $currentTotal) * 100;
        return [
            'total' => $currentTotal,
            'changes_compared_last_month' => $changesSinceLastMonthPercent,
            'increased' => $changesSinceLastMonthPercent > 0,
        ];
    }

    protected function getActiveUserTotal(){
        // get active user by view activity from last month
        $currentTotal = View::where('created_at', '>', now()->startOfMonth())->distinct('user_id')->selectRaw('COUNT(DISTINCT user_id) as total')->value('total');

        // get active user by view activity from last 1 months
        $upToLastMonthTotal = View::where('created_at', '>', now()->subMonth()->startOfMonth())->distinct('user_id')->selectRaw('COUNT(DISTINCT user_id) as total')->value('total');

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
        $currentTotal = Article::published()->selectRaw('COUNT(*) as total')->value('total');
        // last month total
        $upToLastMonthTotal = Article::published()->where('created_at', '<', now()->subMonth())->selectRaw('COUNT(*) as total')->value('total');

        $changesSinceLastMonthPercent = (($currentTotal - $upToLastMonthTotal) / $currentTotal) * 100;
        return [
            'total' => $currentTotal,
            'changes_compared_last_month' => $changesSinceLastMonthPercent,
            'increased' => $changesSinceLastMonthPercent > 0,
        ];
    }

    protected function getCards(): array
    {
        $userData = $this->getUserTotal();
        $activeUserData = $this->getActiveUserTotal();

        $articleData = $this->getArticlesPublished();

        return [
            Card::make('Total Users', $this->number_format_short($userData['total']))
                ->description($this->getChangeMessage($userData['changes_compared_last_month']))
                ->descriptionIcon($this->getChangeIcon($userData['changes_compared_last_month']))
                ->color($this->getChangeColor($userData['changes_compared_last_month'])),

            Card::make('Active Users', $this->number_format_short($activeUserData['total']))
                ->description($this->getChangeMessage($activeUserData['changes_compared_last_month']))
                ->descriptionIcon($this->getChangeIcon($activeUserData['changes_compared_last_month']))
                ->color($this->getChangeColor($activeUserData['changes_compared_last_month'])),

            Card::make('Total Published Articles', number_format($articleData['total']))
                ->description($this->getChangeMessage($articleData['changes_compared_last_month']))
                ->descriptionIcon($this->getChangeIcon($articleData['changes_compared_last_month']))
                ->color($this->getChangeColor($articleData['changes_compared_last_month'])),
        ];
    }

    protected function getChangeMessage($value) {
        if ($value > 0) {
            return $this->number_format_short(abs($value)). '% increased from last month';
        } else if ($value < 0) {
            return $this->number_format_short(abs($value)). '% decreased from last month';
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

    protected function number_format_short( $n, $precision = 1 ) {
        if ($n < 900) {
            // 0 - 900
            $n_format = number_format($n, $precision);
            $suffix = '';
        } else if ($n < 900000) {
            // 0.9k-850k
            $n_format = number_format($n / 1000, $precision);
            $suffix = 'K';
        } else if ($n < 900000000) {
            // 0.9m-850m
            $n_format = number_format($n / 1000000, $precision);
            $suffix = 'M';
        } else if ($n < 900000000000) {
            // 0.9b-850b
            $n_format = number_format($n / 1000000000, $precision);
            $suffix = 'B';
        } else {
            // 0.9t+
            $n_format = number_format($n / 1000000000000, $precision);
            $suffix = 'T';
        }

      // Remove unecessary zeroes after decimal. "1.0" -> "1"; "1.00" -> "1"
      // Intentionally does not affect partials, eg "1.50" -> "1.50"
        if ( $precision > 0 ) {
            $dotzero = '.' . str_repeat( '0', $precision );
            $n_format = str_replace( $dotzero, '', $n_format );
        }

        return $n_format . $suffix;
    }

}
