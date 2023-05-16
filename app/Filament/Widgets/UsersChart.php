<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\LineChartWidget;

class UsersChart extends LineChartWidget
{
    protected static ?string $heading = 'Users';
    protected static ?array $options = [
        'ticks' => [
            'precision' => 0
        ],
    ];

    protected function getData(): array
    {
        // get User count across period of 12 months
        $data = User::withoutGlobalScope(SoftDeletingScope::class)
            ->where('created_at', '>=', now()->subMonths(12))
            ->get()
            ->groupBy(function ($user) {
                return $user->created_at->format('M');
            })
            ->map(function ($user) {
                return $user->count();
            });
    
        return [
            'datasets' => [
                [
                    'label' => 'Users',
                    'data' => $data->values(),
                ],
            ],
            'labels' => $data->keys(),
        ];
    }
}
