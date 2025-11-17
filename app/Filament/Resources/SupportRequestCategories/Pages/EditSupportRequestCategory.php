<?php

namespace App\Filament\Resources\SupportRequestCategories\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\SupportRequestCategories\SupportRequestCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupportRequestCategory extends EditRecord
{
    protected static string $resource = SupportRequestCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
