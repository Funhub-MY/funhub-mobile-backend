<?php

namespace App\Filament\Resources\ArticleFeedWhitelistUsers\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\ArticleFeedWhitelistUsers\ArticleFeedWhitelistUserResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditArticleFeedWhitelistUser extends EditRecord
{
    protected static string $resource = ArticleFeedWhitelistUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->record->user;
        $data['user_id'] = $user ? "{$user->name} ({$user->username})" : null;
        return $data;
    }
}
