<?php

namespace App\Filament\Resources\UserHistoricalLocations\Pages;

use App\Filament\Resources\UserHistoricalLocations\UserHistoricalLocationResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUserHistoricalLocation extends CreateRecord
{
    protected static string $resource = UserHistoricalLocationResource::class;
}
