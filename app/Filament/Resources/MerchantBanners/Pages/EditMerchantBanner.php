<?php

namespace App\Filament\Resources\MerchantBanners\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\MerchantBanners\MerchantBannerResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMerchantBanner extends EditRecord
{
    protected static string $resource = MerchantBannerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
