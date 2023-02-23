<?php

namespace App\Filament\Resources\MerchantResource\Pages;

use App\Filament\Resources\MerchantResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMerchant extends EditRecord
{
    protected static string $resource = MerchantResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
