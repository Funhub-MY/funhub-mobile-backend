<?php

namespace App\Filament\Resources\ArticleStoreCategoryResource\Pages;

use App\Filament\Resources\ArticleStoreCategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditArticleStoreCategory extends EditRecord
{
    protected static string $resource = ArticleStoreCategoryResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
