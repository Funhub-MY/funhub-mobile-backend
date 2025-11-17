<?php

namespace App\Filament\Resources\ArticleStoreCategories\Pages;

use App\Filament\Resources\ArticleStoreCategories\ArticleStoreCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateArticleStoreCategory extends CreateRecord
{
    protected static string $resource = ArticleStoreCategoryResource::class;
}
