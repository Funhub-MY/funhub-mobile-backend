<?php

namespace App\Filament\Resources\ArticleFeedWhitelistUserResource\Pages;

use App\Filament\Resources\ArticleFeedWhitelistUserResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditArticleFeedWhitelistUser extends EditRecord
{
    protected static string $resource = ArticleFeedWhitelistUserResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
