<?php

namespace App\Filament\Resources\SupportRequestCategories\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\SupportRequestCategories\SupportRequestCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSupportRequestCategories extends ListRecords
{
    protected static string $resource = SupportRequestCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
