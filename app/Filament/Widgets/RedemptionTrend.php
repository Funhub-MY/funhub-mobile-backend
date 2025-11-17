<?php

namespace App\Filament\Widgets;

use App\Models\MerchantOfferClaim;
use App\Models\MerchantOfferClaimRedemptions;
use App\Traits\HasPeriodTrait;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms\Components\Select;
use Filament\Widgets\LineChartWidget;

class RedemptionTrend extends LineChartWidget
{
    use HasPeriodTrait, HasWidgetShield;

    protected ?string $heading = 'Voucher Redemptions';
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

        // Get redemptions from the last 12 months
        $redeems = MerchantOfferClaimRedemptions::where('created_at', '>=', now()->subMonths(12))
            ->get();

        // Get unredeemed claims from the last 12 months
        $unredeemed = MerchantOfferClaim::doesntHave('redeem')
            ->where('created_at', '>=', now()->subMonths(12))
            ->get();

        $periods = $this->getPeriods($selectedPeriod);

        // Count redemptions for each period
        $redeemsData = $periods->map(function ($period) use ($redeems, $selectedPeriod) {
            return $redeems->filter(function ($redeem) use ($period, $selectedPeriod) {
                if ($selectedPeriod == 7 || $selectedPeriod == 1) {
                    return $redeem->created_at->format('d/m/Y') == $period;
                } else {
                    return $redeem->created_at->format('M Y') == $period;
                }
            })->count();
        })->toArray();

        // Count unredeemed claims for each period
        $unredeemedData = $periods->map(function ($period) use ($unredeemed, $selectedPeriod) {
            return $unredeemed->filter(function ($claim) use ($period, $selectedPeriod) {
                if ($selectedPeriod == 7 || $selectedPeriod == 1) {
                    return $claim->created_at->format('d/m/Y') == $period;
                } else {
                    return $claim->created_at->format('M Y') == $period;
                }
            })->count();
        })->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'No. Of Redemptions of Purchased Vouchers',
                    'data' => $redeemsData,
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'No. Of Unredeemed Purchased Vouchers',
                    'data' => $unredeemedData,
                    'borderColor' => '#ccc',
                    'backgroundColor' => 'rgba(204, 204, 204, 0.1)',
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