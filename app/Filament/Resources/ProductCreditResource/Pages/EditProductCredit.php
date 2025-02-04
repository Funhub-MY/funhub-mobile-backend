<?php

namespace App\Filament\Resources\ProductCreditResource\Pages;

use App\Filament\Resources\ProductCreditResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductCredit extends EditRecord
{
    protected static string $resource = ProductCreditResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
