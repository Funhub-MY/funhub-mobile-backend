<?php

namespace App\Filament\Widgets;

use App\Models\MerchantOffer;
use App\Models\Product;
use App\Models\Transaction;
use App\Traits\HasPeriodTrait;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Widgets\LineChartWidget;
use Illuminate\Database\Eloquent\Scopes\SoftDeletingScope;

class TransactionAmountByPeriod extends LineChartWidget
{
    use HasWidgetShield, HasPeriodTrait;

    protected static ?string $heading = 'Transaction Amount (RM)';
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

        // Get transaction amounts for offers
        $offers = Transaction::withoutGlobalScope(SoftDeletingScope::class)
            ->where('created_at', '>=', now()->subMonths(12))
            ->where('transactionable_type', MerchantOffer::class)
            ->where('status', $selectedStatus)
            ->get();

        // Get transaction amounts for products
        $products = Transaction::withoutGlobalScope(SoftDeletingScope::class)
            ->where('created_at', '>=', now()->subMonths(12))
            ->where('transactionable_type', Product::class)
            ->where('status', $selectedStatus)
            ->get();

        $periods = $this->getPeriods($selectedPeriod);

        // Map amounts for offers by period
        $offersData = $periods->map(function ($period) use ($offers, $selectedPeriod) {
            if ($selectedPeriod == 1 || $selectedPeriod == 7) {
                $amount = $offers->whereBetween('created_at', [
                    Carbon::createFromFormat('d/m/Y', $period)->startOfDay(),
                    Carbon::createFromFormat('d/m/Y', $period)->endOfDay()
                ])->sum('amount');
            } else {
                $amount = $offers->whereBetween('created_at', [
                    Carbon::parse($period)->startOfMonth(),
                    Carbon::parse($period)->endOfMonth()
                ])->sum('amount');
            }
            return $amount;
        })->toArray();

        // Map amounts for products by period
        $productsData = $periods->map(function ($period) use ($products, $selectedPeriod) {
            if ($selectedPeriod == 1 || $selectedPeriod == 7) {
                $amount = $products->whereBetween('created_at', [
                    Carbon::createFromFormat('d/m/Y', $period)->startOfDay(),
                    Carbon::createFromFormat('d/m/Y', $period)->endOfDay()
                ])->sum('amount');
            } else {
                $amount = $products->whereBetween('created_at', [
                    Carbon::parse($period)->startOfMonth(),
                    Carbon::parse($period)->endOfMonth()
                ])->sum('amount');
            }
            return $amount;
        })->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Offers (Cash Purchase)',
                    'data' => $offersData,
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Funbox Gift Card',
                    'data' => $productsData,
                    'borderColor' => '#f43f5e',
                    'backgroundColor' => 'rgba(244, 63, 94, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
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
}