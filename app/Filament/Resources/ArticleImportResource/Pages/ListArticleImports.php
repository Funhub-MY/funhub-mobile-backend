<?php

namespace App\Filament\Resources\ArticleImportResource\Pages;

use App\Filament\Resources\ArticleImportResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListArticleImports extends ListRecords
{
    protected static string $resource = ArticleImportResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
