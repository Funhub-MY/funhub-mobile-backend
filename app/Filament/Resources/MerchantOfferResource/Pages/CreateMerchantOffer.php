<?php

namespace App\Filament\Resources\MerchantOfferResource\Pages;

use App\Filament\Resources\MerchantOfferResource;
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
}
