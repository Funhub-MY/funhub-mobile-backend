<?php

namespace App\Filament\Resources\ArticleImportResource\Pages;

use App\Filament\Resources\ArticleImportResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditArticleImport extends EditRecord
{
    protected static string $resource = ArticleImportResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
