<?php

namespace App\Filament\Resources\UserContacts\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\UserContacts\UserContactResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserContact extends EditRecord
{
    protected static string $resource = UserContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
