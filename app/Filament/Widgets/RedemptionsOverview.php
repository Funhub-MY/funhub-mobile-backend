<?php

namespace App\Filament\Widgets;

use App\Models\MerchantOfferVoucher;
use Filament\Forms\Components\DatePicker;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class RedemptionsOverview extends BaseWidget
{
    protected int | string | array $columnSpan = '1';

    protected function getCards(): array
    {
        $data = MerchantOfferVoucher::whereHas('merchant_offer', function ($q) {
            $q->where('user_id', auth()->id());
        })->whereNotNull('owned_by_id')->count();

        return [
            Card::make('Total Redemptions', $data)
                ->color('success'),
        ];
    }
}
