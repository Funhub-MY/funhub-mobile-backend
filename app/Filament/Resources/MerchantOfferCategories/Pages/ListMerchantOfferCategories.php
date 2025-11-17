<?php

namespace App\Filament\Resources\MerchantOfferCategories\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\MerchantOfferCategories\MerchantOfferCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMerchantOfferCategories extends ListRecords
{
    protected static string $resource = MerchantOfferCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
