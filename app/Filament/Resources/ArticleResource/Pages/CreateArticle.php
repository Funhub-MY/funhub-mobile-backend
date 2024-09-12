<?php

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Resources\ArticleResource;
use App\Jobs\UpdateArticleTagArticlesCount;
use App\Models\Article;
use Filament\Pages\Actions;
use Illuminate\Database\Eloquent\Model;
use App\Models\ArticleTag;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
        preg_match_all('/#([\p{L}\p{N}_]+)/u', $content, $matches);
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

    protected function handleRecordCreation(array $data): Model
    {
        // article creation.
        $article = $this->getModel()::create($data);

        if (isset($data['video_thumbnail'])) {
            $video_thumbnail = $data['video_thumbnail'];

            $media = $article->addMediaFromDisk($video_thumbnail)
                ->withCustomProperties(['is_cover' => true])
                ->toMediaCollection(Article::MEDIA_COLLECTION_NAME,
                    (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default')),
                );

            Log::info('Media added: ', $media->toArray());

            // Then remove the file from storage
            // Check if the thumbnail exists and then delete it
            if (Storage::exists($video_thumbnail)) {
                Storage::delete($video_thumbnail);
                Log::info('Video thumbnail deleted: ' . $video_thumbnail);
            } else {
                Log::warning('Video thumbnail not found: ' . $video_thumbnail);
            }
        }

        if (isset($data['video'])) {
            $video = $data['video'];

            $media = $article->addMediaFromDisk($video)
                ->withCustomProperties(['is_cover' => false])
                ->toMediaCollection(Article::MEDIA_COLLECTION_NAME,
                    (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default')),
                );

            Log::info('Media added: ', $media->toArray());

            // Then remove the file from storage
            // Check if the video exists and then delete it
            if (Storage::exists($video)) {
                Storage::delete($video);
                Log::info('Video thumbnail deleted: ' . $video);
            } else {
                Log::warning('Video thumbnail not found: ' . $video);
            }
        }

        return $article;
    }
}
