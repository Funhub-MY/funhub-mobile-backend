<?php

namespace App\Filament\Resources\RatingCategories\Pages;

use App\Filament\Resources\RatingCategories\RatingCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateRatingCategory extends CreateRecord
{
    protected static string $resource = RatingCategoryResource::class;
}
