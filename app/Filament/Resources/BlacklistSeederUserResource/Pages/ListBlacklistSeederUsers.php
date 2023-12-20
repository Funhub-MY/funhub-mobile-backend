<?php

namespace App\Filament\Resources\BlacklistSeederUserResource\Pages;

use App\Filament\Resources\BlacklistSeederUserResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBlacklistSeederUsers extends ListRecords
{
    protected static string $resource = BlacklistSeederUserResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
