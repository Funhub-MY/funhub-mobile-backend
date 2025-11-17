<?php

namespace App\Filament\Resources\ArticleFeedWhitelistUsers\Pages;

use Exception;
use App\Filament\Resources\ArticleFeedWhitelistUsers\ArticleFeedWhitelistUserResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateArticleFeedWhitelistUser extends CreateRecord
{
    protected static string $resource = ArticleFeedWhitelistUserResource::class;

    protected function afterCreate(): void
    {
        try {
            // trigger searcheable to reindex
            $this->record->user->articles->each(function ($article) {
                $article->searchable();
            });
            Log::info('[ArticleFeedWhitelistUserResource] After create, user articles added to search index(algolia)', [
                'user_id' => $this->record->user_id,
            ]);
        } catch (Exception $e) {
            Log::error('[ArticleFeedWhitelistUserResource] Unable to index whitelisted user articles', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
