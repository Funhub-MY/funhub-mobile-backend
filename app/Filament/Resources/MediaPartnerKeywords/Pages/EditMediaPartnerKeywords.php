<?php

namespace App\Filament\Resources\MediaPartnerKeywords\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\MediaPartnerKeywords\MediaPartnerKeywordsResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMediaPartnerKeywords extends EditRecord
{
    protected static string $resource = MediaPartnerKeywordsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
