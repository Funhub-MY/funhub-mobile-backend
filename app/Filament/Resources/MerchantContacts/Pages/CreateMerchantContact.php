<?php

namespace App\Filament\Resources\MerchantContacts\Pages;

use App\Filament\Resources\MerchantContacts\MerchantContactResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMerchantContact extends CreateRecord
{
    protected static string $resource = MerchantContactResource::class;
}
