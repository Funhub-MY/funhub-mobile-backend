<?php

namespace App\Filament\Resources\ArticleCategories\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\ArticleCategories\ArticleCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListArticleCategories extends ListRecords
{
    protected static string $resource = ArticleCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
