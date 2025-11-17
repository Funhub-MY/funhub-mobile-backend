<?php

namespace App\Filament\Resources\UserContacts\Pages;

use App\Filament\Resources\UserContacts\UserContactResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUserContact extends CreateRecord
{
    protected static string $resource = UserContactResource::class;
}
