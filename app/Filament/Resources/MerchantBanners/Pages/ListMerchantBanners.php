<?php

namespace App\Filament\Resources\MerchantBanners\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\MerchantBanners\MerchantBannerResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMerchantBanners extends ListRecords
{
    protected static string $resource = MerchantBannerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
