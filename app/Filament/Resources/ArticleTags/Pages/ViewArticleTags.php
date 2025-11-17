<?php

namespace App\Filament\Resources\ArticleTags\Pages;

use App\Filament\Resources\ArticleTags\ArticleTagResource;
use Filament\Resources\Pages\Page;

class ViewArticleTags extends Page
{
    protected $user;

    protected static string $resource = ArticleTagResource::class;

    protected string $view = 'filament.resources.article-tag-resource.pages.view-article-tags';
}
