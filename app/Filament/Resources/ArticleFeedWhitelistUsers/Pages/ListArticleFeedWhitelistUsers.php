<?php

namespace App\Filament\Resources\ArticleFeedWhitelistUsers\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\ArticleFeedWhitelistUsers\ArticleFeedWhitelistUserResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListArticleFeedWhitelistUsers extends ListRecords
{
    protected static string $resource = ArticleFeedWhitelistUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
