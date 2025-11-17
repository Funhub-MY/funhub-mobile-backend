<?php

namespace App\Filament\Resources\MediaPartnerKeywords\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\MediaPartnerKeywords\MediaPartnerKeywordsResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMediaPartnerKeywords extends ListRecords
{
    protected static string $resource = MediaPartnerKeywordsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
