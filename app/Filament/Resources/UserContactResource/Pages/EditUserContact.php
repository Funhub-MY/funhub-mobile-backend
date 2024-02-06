<?php

namespace App\Filament\Resources\UserContactResource\Pages;

use App\Filament\Resources\UserContactResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserContact extends EditRecord
{
    protected static string $resource = UserContactResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
