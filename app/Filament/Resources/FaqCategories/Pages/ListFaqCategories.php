<?php

namespace App\Filament\Resources\FaqCategories\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\FaqCategories\FaqCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFaqCategories extends ListRecords
{
    protected static string $resource = FaqCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
