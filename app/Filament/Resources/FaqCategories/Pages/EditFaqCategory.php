<?php

namespace App\Filament\Resources\FaqCategories\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\FaqCategories\FaqCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFaqCategory extends EditRecord
{
    protected static string $resource = FaqCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
