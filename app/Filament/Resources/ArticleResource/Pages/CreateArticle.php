<?php

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Resources\ArticleResource;
use App\Jobs\UpdateArticleTagArticlesCount;
use App\Models\Article;
use App\Models\ArticleTag;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateArticle extends CreateRecord
{
    protected static string $resource = ArticleResource::class;

    protected function afterCreate(): void
    {
        $article = $this->record;

        // Attach categories, do not create new categories if not exist
        if (isset($this->data['categories']) && !empty($this->data['categories'])) {
            $article->categories()->sync($this->data['categories']);
        }

        // Extract hashtags from the content
        $content = $article->body;
        preg_match_all('/#(\w+)/', $content, $matches);
        $hashtags = $matches[0];

        // Attach or create tags based on detected hashtags
        if (!empty($hashtags)) {
            $tags = collect($hashtags)->map(function ($tag) {
                return ArticleTag::firstOrCreate(['name' => $tag, 'user_id' => auth()->id()])->id;
            });
            // Sync the tags with the article
            $article->tags()->syncWithoutDetaching($tags);

            $tags->each(function ($tagId) {
                $tag = ArticleTag::find($tagId);
                UpdateArticleTagArticlesCount::dispatch($tag);
            });
        }

        $tags = $article->tags;

        // Dispatch the job for each tag associated with the article
        if (!empty($tags)) {
            $tags->each(function ($tag) {
                Log::info('Create Article Firing job for tag: ' . $tag->name);
                UpdateArticleTagArticlesCount::dispatch($tag);
            });
        }

        if ($this->data['locations']) {
            $this->record->location()->sync($this->data['locations']);
        }

        // if published
        if($this->data['status'] == Article::STATUS_PUBLISHED) {
            // fire ArticleCreated event
            event(new \App\Events\ArticleCreated($this->record));
        }
    }

}
