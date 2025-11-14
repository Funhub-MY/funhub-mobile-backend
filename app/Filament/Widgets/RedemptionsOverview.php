<?php

namespace App\Filament\Widgets;

use App\Models\MerchantOfferVoucher;
use Filament\Forms\Components\DatePicker;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RedemptionsOverview extends BaseWidget
{
    protected int | string | array $columnSpan = '1';

    protected function getStats(): array
    {
        $data = MerchantOfferVoucher::whereHas('merchant_offer', function ($q) {
            $q->where('user_id', auth()->id());
        })->whereNotNull('owned_by_id')->count();

        return [
            Stat::make('Total Redemptions', $data)
                ->color('success'),
        ];
    }
}