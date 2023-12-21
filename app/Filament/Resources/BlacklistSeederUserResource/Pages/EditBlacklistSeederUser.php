<?php

namespace App\Filament\Resources\BlacklistSeederUserResource\Pages;

use App\Filament\Resources\BlacklistSeederUserResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlacklistSeederUser extends EditRecord
{
    protected static string $resource = BlacklistSeederUserResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
