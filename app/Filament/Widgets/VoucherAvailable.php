<?php

namespace App\Filament\Widgets;

use App\Models\MerchantOfferVoucher;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class VoucherAvailable extends BaseWidget
{
    protected int | string | array $columnSpan = '1';

    protected function getCards(): array
    {
        // Optimize query by using whereIn instead of whereHas to avoid memory issues
        // Using whereIn with subquery is more efficient than whereHas for large datasets
        $merchantOfferIds = \App\Models\MerchantOffer::where('user_id', auth()->id())
            ->pluck('id');
        
        // Return 0 if no merchant offers found to avoid querying with empty array
        if ($merchantOfferIds->isEmpty()) {
            $data = 0;
        } else {
            $data = MerchantOfferVoucher::whereIn('merchant_offer_id', $merchantOfferIds)
                ->whereNull('owned_by_id')
                ->count();
        }
        
        return [
            Stat::make('Voucher(s) Available', $data)
                ->color('success'),
        ];
    }
}
