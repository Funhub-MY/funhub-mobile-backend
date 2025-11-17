<?php

namespace App\Filament\Resources\ArticleImports\Pages;

use App\Filament\Resources\ArticleImports\ArticleImportResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateArticleImport extends CreateRecord
{
    protected static string $resource = ArticleImportResource::class;
}
