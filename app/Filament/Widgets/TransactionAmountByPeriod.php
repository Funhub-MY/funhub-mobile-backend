<?php

namespace App\Filament\Widgets;

use App\Models\MerchantOffer;
use App\Models\Product;
use App\Models\Transaction;
use App\Traits\HasPeriodTrait;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class TransactionAmountByPeriod extends ApexChartWidget
{
    use HasWidgetShield, HasPeriodTrait;
    /**
     * Chart Id
     *
     * @var string
     */
    protected static string $chartId = 'transactionAmountByPeriod';
    protected int | string | array $columnSpan = 'full';

    /**
     * Widget Title
     *
     * @var string|null
     */
    protected static ?string $heading = 'Transaction Amount (RM)';

    protected function getFormSchema(): array
    {
        return [
            Select::make('period')
                ->options([
                    12 => '12 Months',
                    6 => '6 Months',
                    3 => '3 Months',
                    1 => '1 Month',
                    7 => '7 Days',
                ])
                ->default('6'),

            Select::make('status')
                ->label('Payment Status')
                ->options([
                    0 => 'Pending',
                    1 => 'Success',
                    2 => 'Failed'
                ])
                ->default(1),
        ];
    }

    /**
     * Chart options (series, labels, types, size, animations...)
     * https://apexcharts.com/docs/options
     *
     * @return array
     */
    protected function getOptions(): array
    {
        //showing a loading indicator immediately after the page load
        if (!$this->readyToLoad) {
            return [];
        }

        $selectedPeriod = $this->filterFormData['period'];
        $selectedStatus = $this->filterFormData['status'];

         // get transactions amount
         $offers =  Transaction::withoutGlobalScope(SoftDeletingScope::class)
            ->where('created_at', '>=', now()->subMonths(12))
            ->where('transactionable_type', MerchantOffer::class)
            ->where('status', $selectedStatus)
            ->get();

        $products = Transaction::withoutGlobalScope(SoftDeletingScope::class)
            ->where('created_at', '>=', now()->subMonths(12))
            ->where('transactionable_type', Product::class)
            ->where('status', $selectedStatus)
            ->get();

        $periods = $this->getPeriods($selectedPeriod);

        // map as two lines one for offer one for product, sum up each month's amount
        $offers = $periods->map(function ($p) use ($offers, $selectedPeriod) {
            if ($selectedPeriod == 1 || $selectedPeriod == 7) {
                $amount = $offers->whereBetween('created_at', [
                    Carbon::createFromFormat('d/m/Y', $p)->startOfDay(),
                    Carbon::createFromFormat('d/m/Y', $p)->endOfDay()])
                    ->sum('amount');
            } else {
                $amount = $offers->whereBetween('created_at', [
                    Carbon::parse($p)->startOfMonth(),
                    Carbon::parse($p)->endOfMonth()])
                    ->sum('amount');
            }
            return $amount;
        });

        $products = $periods->map(function ($p) use ($products, $selectedPeriod) {
            if ($selectedPeriod == 1 || $selectedPeriod == 7) {
                $amount = $products->whereBetween('created_at', [
                    Carbon::createFromFormat('d/m/Y', $p)->startOfDay(),
                    Carbon::createFromFormat('d/m/Y', $p)->endOfDay()])
                    ->sum('amount');
            } else {
                $amount = $products->whereBetween('created_at', [
                    Carbon::parse($p)->startOfMonth(),
                    Carbon::parse($p)->endOfMonth()])
                    ->sum('amount');
            }
            return $amount;
        });

        return [
            'chart' => [
                'type' => 'line',
                'height' => 300,
            ],
            'series' => [
                [
                    'name' => 'Offers (Cash Purchase)',
                    'data' => $offers->toArray(),
                    'color' => '#6366f1',
                ],
                [
                    'name' => 'Funbox Gift Card',
                    'data' => $products->toArray(),
                    'color' => '#f43f5e',
                ],
            ],
            'xaxis' => [
                'categories' => $periods->toArray(),
                'labels' => [
                    'style' => [
                        'colors' => '#9ca3af',
                    ],
                ],
            ],
            'yaxis' => [
                'labels' => [
                    'style' => [
                        'colors' => '#9ca3af',
                    ]
                ],
            ],
            'colors' => ['#6366f1'],
            'stroke' => [
                'curve' => 'smooth',
            ],
        ];
    }
}
