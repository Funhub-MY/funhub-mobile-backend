<?php

namespace App\Filament\Resources\MerchantBanners\Pages;

use App\Filament\Resources\MerchantBanners\MerchantBannerResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMerchantBanner extends CreateRecord
{
    protected static string $resource = MerchantBannerResource::class;
}
