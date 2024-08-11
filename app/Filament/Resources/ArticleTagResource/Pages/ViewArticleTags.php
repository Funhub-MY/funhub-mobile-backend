<?php

namespace App\Filament\Resources\ArticleTagResource\Pages;

use App\Filament\Resources\ArticleTagResource;
use Filament\Resources\Pages\Page;

class ViewArticleTags extends Page
{
    protected $user;

    protected static string $resource = ArticleTagResource::class;

    protected static string $view = 'filament.resources.article-tag-resource.pages.view-article-tags';
}
