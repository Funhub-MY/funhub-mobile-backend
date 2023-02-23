<?php

namespace App\Filament\Resources\MerchantOfferResource\Pages;

use App\Filament\Resources\MerchantOfferResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMerchantOffer extends EditRecord
{
    protected static string $resource = MerchantOfferResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
