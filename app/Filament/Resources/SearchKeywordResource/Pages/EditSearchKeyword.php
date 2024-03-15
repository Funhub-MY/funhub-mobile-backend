<?php

namespace App\Filament\Resources\SearchKeywordResource\Pages;

use App\Filament\Resources\SearchKeywordResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSearchKeyword extends EditRecord
{
    protected static string $resource = SearchKeywordResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
