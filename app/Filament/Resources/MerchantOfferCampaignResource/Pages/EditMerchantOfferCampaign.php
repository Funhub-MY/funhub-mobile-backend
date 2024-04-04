<?php

namespace App\Filament\Resources\MerchantOfferCampaignResource\Pages;

use App\Filament\Resources\MerchantOfferCampaignResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMerchantOfferCampaign extends EditRecord
{
    protected static string $resource = MerchantOfferCampaignResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
