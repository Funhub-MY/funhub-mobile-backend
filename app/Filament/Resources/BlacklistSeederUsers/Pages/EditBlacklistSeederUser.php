<?php

namespace App\Filament\Resources\BlacklistSeederUsers\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\BlacklistSeederUsers\BlacklistSeederUserResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlacklistSeederUser extends EditRecord
{
    protected static string $resource = BlacklistSeederUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
