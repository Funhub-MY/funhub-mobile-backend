<?php

namespace App\Filament\Resources\RatingCategories\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\RatingCategories\RatingCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRatingCategory extends EditRecord
{
    protected static string $resource = RatingCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
