<?php

namespace App\Filament\Resources\AutoArchiveKeywords\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\AutoArchiveKeywords\AutoArchiveKeywordResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAutoArchiveKeywords extends ListRecords
{
    protected static string $resource = AutoArchiveKeywordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
