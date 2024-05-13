<?php

namespace App\Filament\Resources\ArticleEngagementResource\Pages;

use App\Filament\Resources\ArticleEngagementResource;
use App\Jobs\ProcessEngagementInteractions;
use Filament\Resources\Pages\CreateRecord;

class CreateArticleEngagement extends CreateRecord
{
    protected static string $resource = ArticleEngagementResource::class;

    protected function afterCreate(): void
    {
        $engagement = $this->record;

        if ($engagement->scheduled_at !== null && $engagement->scheduled_at->isFuture()) {
            ProcessEngagementInteractions::dispatch($engagement)->delay($engagement->scheduled_at);
        } else {
            ProcessEngagementInteractions::dispatch($engagement);
        }
    }
}
