<?php

namespace App\Filament\Resources\SearchKeywords\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\SearchKeywords\SearchKeywordResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSearchKeywords extends ListRecords
{
    protected static string $resource = SearchKeywordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
