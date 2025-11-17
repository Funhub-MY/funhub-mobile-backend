<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Traits\HasPeriodTrait;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms\Components\Select;
use Filament\Widgets\LineChartWidget;
use Illuminate\Database\Eloquent\Scopes\SoftDeletingScope;

class ProductsSales extends LineChartWidget
{
    use HasWidgetShield, HasPeriodTrait;

    protected ?string $heading = 'Products (Gift Card) Sales Volume';
    protected int | string | array $columnSpan = 'full';

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
                ->default(6),

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

    protected function getData(): array
    {
        if (!isset($this->filterFormData) || empty($this->filterFormData)) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $selectedPeriod = $this->filterFormData['period'] ?? 6;
        $selectedStatus = $this->filterFormData['status'] ?? 1;

        // Get all products
        $products = Product::all();
        $periods = $this->getPeriods($selectedPeriod);

        // Color palette for each product
        $colorCodes = [
            '#6366f1', '#f43f5e', '#20c997', '#6c757d', '#ffc107', 
            '#6f42c1', '#fd7e14', '#e83e8c', '#17a2b8', '#007bff', 
            '#28a745', '#dc3545',
        ];

        $datasets = [];

        foreach ($products as $index => $product) {
            $product_sales = $product->transactions()
                ->where('created_at', '>=', now()->subMonths($selectedPeriod))
                ->where('status', $selectedStatus)
                ->get();

            $data = collect($periods)->map(function ($period) use ($product_sales, $selectedPeriod, $product) {
                return $product_sales->filter(function ($product_sale) use ($period, $selectedPeriod, $product) {
                    if ($selectedPeriod == 7 || $selectedPeriod == 1) {
                        return $product_sale->created_at->format('d/m/Y') == $period && 
                               $product_sale->transactionable_id == $product->id;
                    } else {
                        return $product_sale->created_at->format('M Y') == $period && 
                               $product_sale->transactionable_id == $product->id;
                    }
                })->count();
            })->toArray();

            $color = $colorCodes[$index % count($colorCodes)];
            
            $datasets[] = [
                'label' => $product->name,
                'data' => $data,
                'borderColor' => $color,
                'backgroundColor' => $this->hexToRgba($color, 0.1),
                'fill' => true,
                'tension' => 0.4,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $periods->toArray(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }

    /**
     * Convert hex color to rgba
     */
    private function hexToRgba(string $hex, float $alpha = 1): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "rgba($r, $g, $b, $alpha)";
    }
}