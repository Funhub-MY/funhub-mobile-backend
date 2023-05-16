<?php

namespace App\Filament\Resources\ArticleTagResource\Pages;

use App\Filament\Resources\ArticleTagResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListArticleTags extends ListRecords
{
    protected static string $resource = ArticleTagResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
