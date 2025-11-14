<?php

namespace App\Filament\Resources\MerchantOfferVoucherResource\Pages;

use App\Filament\Resources\MerchantOfferVoucherResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMerchantOfferVoucher extends EditRecord
{
    protected static string $resource = MerchantOfferVoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
