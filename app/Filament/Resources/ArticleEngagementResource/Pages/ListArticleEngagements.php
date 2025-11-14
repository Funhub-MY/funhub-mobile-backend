<?php

namespace App\Filament\Resources\ArticleEngagementResource\Pages;

use App\Filament\Resources\ArticleEngagementResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListArticleEngagements extends ListRecords
{
    protected static string $resource = ArticleEngagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
