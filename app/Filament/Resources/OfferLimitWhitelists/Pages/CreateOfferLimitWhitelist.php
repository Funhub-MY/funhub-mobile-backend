<?php

namespace App\Filament\Resources\OfferLimitWhitelists\Pages;

use App\Filament\Resources\OfferLimitWhitelists\OfferLimitWhitelistResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateOfferLimitWhitelist extends CreateRecord
{
    protected static string $resource = OfferLimitWhitelistResource::class;
}
