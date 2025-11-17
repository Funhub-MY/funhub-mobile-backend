<?php

namespace App\Filament\Resources\MerchantContacts\Pages;

use App\Filament\Resources\MerchantContacts\MerchantContactResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMerchantContact extends EditRecord
{
    protected static string $resource = MerchantContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //Actions\DeleteAction::make(),
        ];
    }
}
