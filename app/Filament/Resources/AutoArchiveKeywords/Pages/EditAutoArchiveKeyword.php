<?php

namespace App\Filament\Resources\AutoArchiveKeywords\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\AutoArchiveKeywords\AutoArchiveKeywordResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAutoArchiveKeyword extends EditRecord
{
    protected static string $resource = AutoArchiveKeywordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
