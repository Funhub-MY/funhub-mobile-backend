<?php

namespace App\Filament\Widgets;

use App\Models\MerchantOfferClaim;
use App\Models\MerchantOfferClaimRedemptions;
use App\Traits\HasPeriodTrait;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms\Components\Select;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class RedemptionTrend extends ApexChartWidget
{
    use HasPeriodTrait, HasWidgetShield;
    /**
     * Chart Id
     *
     * @var string
     */
    protected static string $chartId = 'redemptionTrend';
    protected int | string | array $columnSpan = 'full';

    /**
     * Widget Title
     *
     * @var string|null
     */
    protected static ?string $heading = 'Voucher Redemptions';

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
        $selectedPeriod = $this->filterFormData['period'];

        $redeems = MerchantOfferClaimRedemptions::where('created_at', '>=', now()->subMonths(12))
            ->get();

        // undeemed claims
        $unredeemed = MerchantOfferClaim::doesntHave('redeem')
            ->where('created_at', '>=', now()->subMonths(12))
            ->get();

        $periods = $this->getPeriods($selectedPeriod);

        // count no. of redeems for period
        $redeems = $periods->map(function ($period) use ($redeems, $selectedPeriod) {
            $count = $redeems->filter(function ($redeem) use ($period, $selectedPeriod) {
                if ($selectedPeriod == 7 || $selectedPeriod == 1) {
                    return $redeem->created_at->format('d/m/Y') == $period;
                } else {
                    return $redeem->created_at->format('M Y') == $period;
                }
            })->count();

            return $count;
        })->toArray();

        // count no .of unredeemed claims for period
        $unredeemed = $periods->map(function ($period) use ($unredeemed, $selectedPeriod) {
            $count = $unredeemed->filter(function ($unredeemed) use ($period, $selectedPeriod) {
                if ($selectedPeriod == 7 || $selectedPeriod == 1) {
                    return $unredeemed->created_at->format('d/m/Y') == $period;
                } else {
                    return $unredeemed->created_at->format('M Y') == $period;
                }
            })->count();

            return $count;
        })->toArray();

        return [
            'chart' => [
                'type' => 'line',
                'height' => 300,
            ],
            'series' => [
                [
                    'name' => 'No. Of Redemptions of Purchased Vouchers',
                    'data' => $redeems,
                ],
                [
                    'name' => 'No. Of Unredeemed Purchased Vouchers',
                    'data' => $unredeemed,
                    'color' => '#ccc'
                ],
            ],
            'xaxis' => [
                'categories' => $periods->toArray(),
                'labels' => [
                    'style' => [
                        'colors' => '#b8c2cc',
                        'fontWeight' => 500,
                    ],
                ],
            ],
            'yaxis' => [
                'labels' => [
                    'style' => [
                        'colors' => '#9ca3af',
                        'fontWeight' => 500,
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
