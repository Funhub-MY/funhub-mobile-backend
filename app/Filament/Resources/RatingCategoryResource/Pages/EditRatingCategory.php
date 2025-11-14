<?php

namespace App\Filament\Resources\RatingCategoryResource\Pages;

use App\Filament\Resources\RatingCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRatingCategory extends EditRecord
{
    protected static string $resource = RatingCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
