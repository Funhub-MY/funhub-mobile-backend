<?php

namespace App\Filament\Resources\MerchantOfferResource\Pages;

use App\Filament\Resources\MerchantOfferResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMerchantOffers extends ListRecords
{
    protected static string $resource = MerchantOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
