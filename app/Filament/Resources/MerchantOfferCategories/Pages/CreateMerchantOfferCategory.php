<?php

namespace App\Filament\Resources\MerchantOfferCategories\Pages;

use App\Filament\Resources\MerchantOfferCategories\MerchantOfferCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMerchantOfferCategory extends CreateRecord
{
    protected static string $resource = MerchantOfferCategoryResource::class;
}
