<?php

namespace App\Filament\Resources\ArticleEngagementResource\Pages;

use App\Filament\Resources\ArticleEngagementResource;
use App\Jobs\ProcessEngagementInteractions;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;

class CreateArticleEngagement extends CreateRecord
{
    protected static string $resource = ArticleEngagementResource::class;

    protected function afterCreate(): void
    {
        $engagement = $this->record;

        if ($engagement->scheduled_at == null) {
            ProcessEngagementInteractions::dispatch($engagement);
        }
    }
}
