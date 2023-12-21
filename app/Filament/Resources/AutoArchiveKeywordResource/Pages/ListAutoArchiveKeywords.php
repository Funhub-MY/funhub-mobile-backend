<?php

namespace App\Filament\Resources\AutoArchiveKeywordResource\Pages;

use App\Filament\Resources\AutoArchiveKeywordResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAutoArchiveKeywords extends ListRecords
{
    protected static string $resource = AutoArchiveKeywordResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
