<?php

namespace App\Filament\Resources\MediaPartnerKeywordsResource\Pages;

use App\Filament\Resources\MediaPartnerKeywordsResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMediaPartnerKeywords extends ListRecords
{
    protected static string $resource = MediaPartnerKeywordsResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
