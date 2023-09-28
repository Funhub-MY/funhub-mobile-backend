<?php

namespace App\Filament\Widgets;

use App\Models\MerchantOffer;
use App\Models\MerchantOfferClaim;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class RedemptionsValue extends BaseWidget
{
    protected int | string | array $columnSpan = '1';

    protected function getCards(): array
    {
        $data = MerchantOfferClaim::whereHas('merchantOffer', function ($q) {
            $q->where('user_id', auth()->id());
        })->where('status', MerchantOfferClaim::CLAIM_SUCCESS)
        ->sum('net_amount');

        return [
            Card::make('Total Redemption Value (MYR)', $data)
                ->color('success'),
        ];
    }
}
