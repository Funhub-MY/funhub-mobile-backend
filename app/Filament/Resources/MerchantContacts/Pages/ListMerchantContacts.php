<?php

namespace App\Filament\Resources\MerchantContacts\Pages;

use App\Filament\Resources\MerchantContacts\MerchantContactResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMerchantContacts extends ListRecords
{
    protected static string $resource = MerchantContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //Actions\CreateAction::make(),
        ];
    }
}
