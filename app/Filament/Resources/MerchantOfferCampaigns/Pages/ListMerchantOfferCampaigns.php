<?php

namespace App\Filament\Resources\MerchantOfferCampaigns\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\MerchantOfferCampaigns\MerchantOfferCampaignResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMerchantOfferCampaigns extends ListRecords
{
    protected static string $resource = MerchantOfferCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
