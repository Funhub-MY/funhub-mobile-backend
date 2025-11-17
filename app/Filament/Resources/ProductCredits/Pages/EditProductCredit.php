<?php

namespace App\Filament\Resources\ProductCredits\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\ProductCredits\ProductCreditResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductCredit extends EditRecord
{
    protected static string $resource = ProductCreditResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
