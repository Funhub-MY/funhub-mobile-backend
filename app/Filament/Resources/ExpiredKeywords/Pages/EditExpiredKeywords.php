<?php

namespace App\Filament\Resources\ExpiredKeywords\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\ExpiredKeywords\ExpiredKeywordsResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExpiredKeywords extends EditRecord
{
    protected static string $resource = ExpiredKeywordsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
