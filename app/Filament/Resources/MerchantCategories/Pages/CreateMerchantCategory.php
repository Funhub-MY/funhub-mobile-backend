<?php

namespace App\Filament\Resources\MerchantCategories\Pages;

use App\Filament\Resources\MerchantCategories\MerchantCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMerchantCategory extends CreateRecord
{
    protected static string $resource = MerchantCategoryResource::class;
}
