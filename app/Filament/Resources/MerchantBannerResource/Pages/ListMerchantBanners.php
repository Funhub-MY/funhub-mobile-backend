<?php

namespace App\Filament\Resources\MerchantBannerResource\Pages;

use App\Filament\Resources\MerchantBannerResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMerchantBanners extends ListRecords
{
    protected static string $resource = MerchantBannerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
