<?php

namespace App\Filament\Resources\MerchantOfferWhitelists\Pages;

use App\Filament\Resources\MerchantOfferWhitelists\MerchantOfferWhitelistResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMerchantOfferWhitelists extends ListRecords
{
    protected static string $resource = MerchantOfferWhitelistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
