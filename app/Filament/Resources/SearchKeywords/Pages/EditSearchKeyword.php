<?php

namespace App\Filament\Resources\SearchKeywords\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\SearchKeywords\SearchKeywordResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSearchKeyword extends EditRecord
{
    protected static string $resource = SearchKeywordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
