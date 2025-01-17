<?php

namespace App\Filament\Resources\ExpiredKeywordsResource\Pages;

use App\Filament\Resources\ExpiredKeywordsResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExpiredKeywords extends ListRecords
{
    protected static string $resource = ExpiredKeywordsResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
