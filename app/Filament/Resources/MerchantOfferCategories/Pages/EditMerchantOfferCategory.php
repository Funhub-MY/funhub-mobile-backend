<?php

namespace App\Filament\Resources\MerchantOfferCategories\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\MerchantOfferCategories\MerchantOfferCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMerchantOfferCategory extends EditRecord
{
    protected static string $resource = MerchantOfferCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
