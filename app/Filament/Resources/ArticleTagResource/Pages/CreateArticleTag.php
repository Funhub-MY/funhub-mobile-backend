<?php

namespace App\Filament\Resources\ArticleTagResource\Pages;

use App\Filament\Resources\ArticleTagResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateArticleTag extends CreateRecord
{
    protected static string $resource = ArticleTagResource::class;
}
