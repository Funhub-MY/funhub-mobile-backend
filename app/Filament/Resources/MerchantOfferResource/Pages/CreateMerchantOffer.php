<?php

namespace App\Filament\Resources\MerchantOfferResource\Pages;

use App\Filament\Resources\MerchantOfferResource;
use App\Models\MerchantOfferVoucher;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMerchantOffer extends CreateRecord
{
    protected static string $resource = MerchantOfferResource::class;


    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['currency'] = 'MYR';
        return $data;
    }
    protected function afterCreate(): void
    {
        // depending on merchant offer quantity specificed, create vouchers
        $record = $this->record;

        $quantity = $record->quantity;
        for($i = 0; $i < $quantity; $i++) {
            MerchantOfferVoucher::create([
                'merchant_offer_id' => $record->id,
                'code' => MerchantOfferVoucher::generateCode(),
            ]);
        }
    }
}
