<?php

namespace App\Filament\Resources\MerchantOfferVouchers\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\MerchantOfferVouchers\MerchantOfferVoucherResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMerchantOfferVoucher extends EditRecord
{
    protected static string $resource = MerchantOfferVoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
