<?php

namespace App\Filament\Resources\MerchantOfferWhitelists\Pages;

use App\Filament\Resources\MerchantOfferWhitelists\MerchantOfferWhitelistResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMerchantOfferWhitelist extends ViewRecord
{
    protected static string $resource = MerchantOfferWhitelistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
