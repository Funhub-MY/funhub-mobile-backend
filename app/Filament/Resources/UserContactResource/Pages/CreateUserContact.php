<?php

namespace App\Filament\Resources\UserContactResource\Pages;

use App\Filament\Resources\UserContactResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUserContact extends CreateRecord
{
    protected static string $resource = UserContactResource::class;
}
