<?php

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Resources\ArticleResource;
use App\Models\Article;
use Filament\Pages\Actions;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
