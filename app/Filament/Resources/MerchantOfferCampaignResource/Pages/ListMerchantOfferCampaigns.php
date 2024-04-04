<?php

namespace App\Filament\Resources\MerchantOfferCampaignResource\Pages;

use App\Filament\Resources\MerchantOfferCampaignResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMerchantOfferCampaigns extends ListRecords
{
    protected static string $resource = MerchantOfferCampaignResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
