<?php

namespace App\Filament\Resources\MerchantCategoryResource\Pages;

use App\Filament\Resources\MerchantCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMerchantCategories extends ListRecords
{
    protected static string $resource = MerchantCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
