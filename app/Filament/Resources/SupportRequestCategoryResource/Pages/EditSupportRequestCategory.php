<?php

namespace App\Filament\Resources\SupportRequestCategoryResource\Pages;

use App\Filament\Resources\SupportRequestCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupportRequestCategory extends EditRecord
{
    protected static string $resource = SupportRequestCategoryResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
