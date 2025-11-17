<?php

namespace App\Filament\Resources\MerchantOffers\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\MerchantOffers\MerchantOfferResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMerchantOffers extends ListRecords
{
    protected static string $resource = MerchantOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
