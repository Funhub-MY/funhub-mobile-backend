<?php

namespace App\Filament\Resources\ArticleEngagements\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\ArticleEngagements\ArticleEngagementResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListArticleEngagements extends ListRecords
{
    protected static string $resource = ArticleEngagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
