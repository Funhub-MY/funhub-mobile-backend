<?php

namespace App\Filament\Resources\MerchantOfferWhitelists\Pages;

use App\Filament\Resources\MerchantOfferWhitelists\MerchantOfferWhitelistResource;
use App\Models\MerchantOffer;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMerchantOfferWhitelist extends EditRecord
{
    protected static string $resource = MerchantOfferWhitelistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
    
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Auto-update merchant_user_id if offer is changed
        if (isset($data['merchant_offer_id'])) {
            $offer = MerchantOffer::find($data['merchant_offer_id']);
            if ($offer && $offer->user_id) {
                $data['merchant_user_id'] = $offer->user_id;
            }
        }
        
        return $data;
    }
}
