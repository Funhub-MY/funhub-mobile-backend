<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\LineChartWidget;
use Illuminate\Support\Facades\Cache;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class UsersChart extends LineChartWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Users';
    protected static ?array $options = [
        'ticks' => [
            'precision' => 0
        ],
    ];
    protected function getData(): array
    {
        // get User count across period of 12 months
        $data =  User::withoutGlobalScope(SoftDeletingScope::class)
        ->where('created_at', '>=', now()->subMonths(12))
        ->get();

        $data = $data->groupBy(function ($user) {
            return $user->created_at->format('M');
        })->map(function ($users) {
            return $users->count();
        });

        // fill in empty months with 0
        $months = collect([
            'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
        ]);
        $data = $months->map(function ($month) use ($data) {
            return $data->get($month, 0);
        });

        $sum = 0;
        $data = $data->map(function ($count) use (&$sum) {
            $sum += $count;
            return $sum;
        });

        return [
            'datasets' => [
                [
                    'label' => 'Users',
                    'data' => $data->values(),
                ],
            ],
            'labels' => $months->values(),
        ];
    }
}
