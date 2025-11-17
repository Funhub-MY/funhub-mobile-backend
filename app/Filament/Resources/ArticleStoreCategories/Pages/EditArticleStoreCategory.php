<?php

namespace App\Filament\Resources\ArticleStoreCategories\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\ArticleStoreCategories\ArticleStoreCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditArticleStoreCategory extends EditRecord
{
    protected static string $resource = ArticleStoreCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
