<?php

namespace App\Filament\Resources\MerchantOfferWhitelists\Pages;

use App\Filament\Resources\MerchantOfferWhitelists\MerchantOfferWhitelistResource;
use App\Models\MerchantOffer;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateMerchantOfferWhitelist extends CreateRecord
{
    protected static string $resource = MerchantOfferWhitelistResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-populate merchant_user_id from the selected offer
        if (isset($data['merchant_offer_id'])) {
            $offer = MerchantOffer::find($data['merchant_offer_id']);
            if ($offer && $offer->user_id) {
                $data['merchant_user_id'] = $offer->user_id;
            }
        }
        
        return $data;
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
