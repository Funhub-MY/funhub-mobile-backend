<?php

namespace App\Filament\Resources\AutoArchiveKeywordResource\Pages;

use App\Filament\Resources\AutoArchiveKeywordResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAutoArchiveKeyword extends EditRecord
{
    protected static string $resource = AutoArchiveKeywordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
