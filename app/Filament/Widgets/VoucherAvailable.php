<?php

namespace App\Filament\Widgets;

use App\Models\MerchantOffer;
use App\Models\MerchantOfferVoucher;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class VoucherAvailable extends BaseWidget
{
    protected int | string | array $columnSpan = '1';

    protected function getCards(): array
    {
        // Optimize query by using merchant_id if available, fallback to user_id
        $merchant = auth()->user()->merchant;
        
        if ($merchant && $merchant->id) {
            // Use merchant_id (preferred - direct relationship)
            $merchantOfferIds = MerchantOffer::where('merchant_id', $merchant->id)
                ->orWhere('user_id', auth()->id()) // Fallback for legacy records
                ->pluck('id');
        } else {
            // Fallback to user_id for users without merchant
            $merchantOfferIds = MerchantOffer::where('user_id', auth()->id())
                ->pluck('id');
        }
        
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
