<?php

namespace App\Filament\Resources\MediaPartnerKeywordsResource\Pages;

use App\Filament\Resources\MediaPartnerKeywordsResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMediaPartnerKeywords extends EditRecord
{
    protected static string $resource = MediaPartnerKeywordsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
