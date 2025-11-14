<?php

namespace App\Filament\Resources\MerchantBannerResource\Pages;

use App\Filament\Resources\MerchantBannerResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMerchantBanner extends EditRecord
{
    protected static string $resource = MerchantBannerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
