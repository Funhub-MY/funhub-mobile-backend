<?php

namespace App\Filament\Resources\ArticleTags\Pages;

use App\Filament\Resources\ArticleTags\ArticleTagResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateArticleTag extends CreateRecord
{
    protected static string $resource = ArticleTagResource::class;
}
