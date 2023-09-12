<?php

namespace App\Filament\Resources\MerchantOfferVoucherResource\Pages;

use App\Filament\Resources\MerchantOfferVoucherResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMerchantOfferVoucher extends CreateRecord
{
    protected static string $resource = MerchantOfferVoucherResource::class;


    protected function afterCreate(): void
    {
        // ensure to increment merchant offer
        $record = $this->record;
        $record->merchant_offer->increment('quantity', 1);
    }
}
