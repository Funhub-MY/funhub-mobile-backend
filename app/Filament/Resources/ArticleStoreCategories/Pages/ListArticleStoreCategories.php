<?php

namespace App\Filament\Resources\ArticleStoreCategories\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\ArticleStoreCategories\ArticleStoreCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListArticleStoreCategories extends ListRecords
{
    protected static string $resource = ArticleStoreCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
