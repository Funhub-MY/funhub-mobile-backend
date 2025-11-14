<?php

namespace App\Filament\Resources\OfferLimitWhitelistResource\Pages;

use App\Filament\Resources\OfferLimitWhitelistResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOfferLimitWhitelists extends ListRecords
{
    protected static string $resource = OfferLimitWhitelistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
