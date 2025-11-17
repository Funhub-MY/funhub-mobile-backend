<?php

namespace App\Filament\Resources\ArticleImports\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\ArticleImports\ArticleImportResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditArticleImport extends EditRecord
{
    protected static string $resource = ArticleImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
