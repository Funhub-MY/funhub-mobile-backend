<?php

namespace App\Filament\Resources\OfferLimitWhitelists\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\OfferLimitWhitelists\OfferLimitWhitelistResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOfferLimitWhitelist extends EditRecord
{
    protected static string $resource = OfferLimitWhitelistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
