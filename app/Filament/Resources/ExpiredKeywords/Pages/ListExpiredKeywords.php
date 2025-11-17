<?php

namespace App\Filament\Resources\ExpiredKeywords\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\ExpiredKeywords\ExpiredKeywordsResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExpiredKeywords extends ListRecords
{
    protected static string $resource = ExpiredKeywordsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
