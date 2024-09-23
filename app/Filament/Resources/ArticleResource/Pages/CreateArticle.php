<?php

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Resources\ArticleResource;
use App\Models\Article;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateArticle extends CreateRecord
{
    protected static string $resource = ArticleResource::class;

    protected function afterCreate(): void
    {
        if($this->data['locations']) {
            $this->record->location()->sync($this->data['locations']);
        }

        // if published
        if($this->data['status'] == Article::STATUS_PUBLISHED) {
            // fire ArticleCreated event
            event(new \App\Events\ArticleCreated($this->record));
        }

        // trigger searcheable to reindex
        $this->record->searchable();
    }

}
