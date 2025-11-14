<?php

namespace App\Filament\Resources\OfferLimitWhitelistResource\Pages;

use App\Filament\Resources\OfferLimitWhitelistResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOfferLimitWhitelist extends EditRecord
{
    protected static string $resource = OfferLimitWhitelistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
