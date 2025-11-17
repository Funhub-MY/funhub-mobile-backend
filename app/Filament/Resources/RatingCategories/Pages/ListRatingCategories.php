<?php

namespace App\Filament\Resources\RatingCategories\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\RatingCategories\RatingCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRatingCategories extends ListRecords
{
    protected static string $resource = RatingCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
