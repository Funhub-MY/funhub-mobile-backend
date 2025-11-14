<?php

namespace App\Filament\Resources\MerchantOfferCategoryResource\Pages;

use App\Filament\Resources\MerchantOfferCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMerchantOfferCategories extends ListRecords
{
    protected static string $resource = MerchantOfferCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
