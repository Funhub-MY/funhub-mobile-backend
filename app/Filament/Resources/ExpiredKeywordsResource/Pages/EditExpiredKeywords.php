<?php

namespace App\Filament\Resources\ExpiredKeywordsResource\Pages;

use App\Filament\Resources\ExpiredKeywordsResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExpiredKeywords extends EditRecord
{
    protected static string $resource = ExpiredKeywordsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
