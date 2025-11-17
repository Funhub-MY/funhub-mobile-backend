<?php

namespace App\Filament\Resources\BlacklistSeederUsers\Pages;

use App\Filament\Resources\BlacklistSeederUsers\BlacklistSeederUserResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateBlacklistSeederUser extends CreateRecord
{
    protected static string $resource = BlacklistSeederUserResource::class;
}
