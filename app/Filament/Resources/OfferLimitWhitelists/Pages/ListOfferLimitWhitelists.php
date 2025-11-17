<?php

namespace App\Filament\Resources\OfferLimitWhitelists\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\OfferLimitWhitelists\OfferLimitWhitelistResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOfferLimitWhitelists extends ListRecords
{
    protected static string $resource = OfferLimitWhitelistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
