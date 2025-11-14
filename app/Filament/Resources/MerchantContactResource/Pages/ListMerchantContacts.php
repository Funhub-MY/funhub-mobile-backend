<?php

namespace App\Filament\Resources\MerchantContactResource\Pages;

use App\Filament\Resources\MerchantContactResource;
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
