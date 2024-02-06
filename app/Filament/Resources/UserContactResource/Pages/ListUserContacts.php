<?php

namespace App\Filament\Resources\UserContactResource\Pages;

use App\Filament\Resources\UserContactResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserContacts extends ListRecords
{
    protected static string $resource = UserContactResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
