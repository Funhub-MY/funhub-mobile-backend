<?php

namespace App\Filament\Resources\MerchantCategories\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\MerchantCategories\MerchantCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMerchantCategory extends EditRecord
{
    protected static string $resource = MerchantCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
