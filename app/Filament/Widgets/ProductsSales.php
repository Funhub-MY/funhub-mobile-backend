<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\Transaction;
use App\Traits\HasPeriodTrait;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class ProductsSales extends ApexChartWidget
{
    use HasWidgetShield, HasPeriodTrait;
    /**
     * Chart Id
     *
     * @var string
     */
    protected static string $chartId = 'productsSales';
    protected int | string | array $columnSpan = 'full';

    /**
     * Widget Title
     *
     * @var string|null
     */
    protected static ?string $heading = 'Products (Gift Card) Sales Volume';

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

        // get products
        $products = Product::all();

        $series = [];

        // array of periods
        $periods = $this->getPeriods($selectedPeriod);

        $colorCodes = ['#6366f1', '#f43f5e', '#20c997', '#6c757d', '#ffc107', '#6f42c1', '#fd7e14', '#e83e8c', '#17a2b8', '#007bff', '#28a745', '#dc3545'];

        foreach ($products as $index => $product) {
            $product_sales = $product->transactions()
                ->where('created_at', '>=', now()->subMonths($selectedPeriod))
                ->where('status', $selectedStatus)
                ->get();

            $series[] = [
                'name' => $product->name,
                'data' => collect($periods)->map(function ($period) use ($product_sales, $selectedPeriod, $product) {
                    $count = $product_sales->filter(function ($product_sale) use ($period, $selectedPeriod, $product) {
                        if ($selectedPeriod == 7 || $selectedPeriod == 1) {
                            return $product_sale->created_at->format('d/m/Y') == $period && $product_sale->transactionable_id == $product->id;
                        } else {
                            return $product_sale->created_at->format('M Y') == $period && $product_sale->transactionable_id == $product->id;
                        }
                    })->count();

                    return $count;
                })->toArray(),
                // pick from colorcodes non repeating
                'color' => $colorCodes[$index],
            ];
        }

        return [
            'chart' => [
                'type' => 'line',
                'height' => 300,
            ],
            'series' => $series,
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
                    ],
                ],
            ],
            'colors' => ['#6366f1'],
            'stroke' => [
                'curve' => 'smooth',
            ],
        ];
    }
}
