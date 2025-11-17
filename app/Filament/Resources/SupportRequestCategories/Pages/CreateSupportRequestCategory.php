<?php

namespace App\Filament\Resources\SupportRequestCategories\Pages;

use App\Filament\Resources\SupportRequestCategories\SupportRequestCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSupportRequestCategory extends CreateRecord
{
    protected static string $resource = SupportRequestCategoryResource::class;
}
