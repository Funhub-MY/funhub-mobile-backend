<?php

namespace App\Filament\Resources\ArticleStoreCategoryResource\Pages;

use App\Filament\Resources\ArticleStoreCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListArticleStoreCategories extends ListRecords
{
    protected static string $resource = ArticleStoreCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
