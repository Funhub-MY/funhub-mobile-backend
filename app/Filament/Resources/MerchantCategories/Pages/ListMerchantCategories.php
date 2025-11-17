<?php

namespace App\Filament\Resources\MerchantCategories\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\MerchantCategories\MerchantCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMerchantCategories extends ListRecords
{
    protected static string $resource = MerchantCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
