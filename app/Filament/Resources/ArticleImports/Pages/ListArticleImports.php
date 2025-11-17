<?php

namespace App\Filament\Resources\ArticleImports\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\ArticleImports\ArticleImportResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListArticleImports extends ListRecords
{
    protected static string $resource = ArticleImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
