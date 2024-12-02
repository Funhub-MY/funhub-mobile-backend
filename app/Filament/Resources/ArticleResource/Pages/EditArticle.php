<?php

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Jobs\UpdateArticleTagArticlesCount;
use App\Models\ArticleTag;
use Filament\Pages\Actions;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    // Store the original tags to compare after editing
    protected $originalTags;

    // Capture original tags before the form is loaded
    protected function beforeFill(): void
    {
        $this->originalTags = $this->record->tags->pluck('id')->toArray();
    }

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update($data);

        // link relation for subCategories
        if ($data['sub_categories']) {
            // only detach categories with parent_id (detaches all sub categories)
            $record->categories->each(function ($category) use ($record) {
                if ($category->parent_id) {
                    $record->categories()->detach($category->id);
                }
            });
            $record->subCategories()->attach($data['sub_categories'], ['created_at' => now(), 'updated_at' => now()]);
        }

        if ($data['locations']) {
            $record->location()->sync($data['locations']);
        }

        return $record;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Fetch fresh media for video thumbnail
        $videoThumbnail = $this->record->getMedia(Article::MEDIA_COLLECTION_NAME)
            ->where('custom_properties.is_cover', true)
            ->first();
        $data['video_thumbnail'] = $videoThumbnail ? $videoThumbnail->getPath() : null;

        // Fetch fresh media for video
        $video = $this->record->getMedia(Article::MEDIA_COLLECTION_NAME)
            ->where('custom_properties.is_cover', false)
            ->first();
        $data['video'] = $video ? $video->getPath() : null;

        $data['sub_categories'] = $this->record->subCategories->pluck('id')->toArray();
        $data['locations'] = $this->record->location->pluck('id')->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $article = $this->record;

        if ($article->type === 'video'){
            // Handle Video Thumbnail
            if (isset($data['video_thumbnail'])) {
                $disk = config('filesystems.default');
                if ($disk == 's3') {
                    $disk = 's3_public';
                }

                Log::info('Video thumbnail: ', ['video_thumbnail' => $data['video_thumbnail']]);

                // Save existing media to a temporary directory
                $tempDir = Storage::disk($disk)->path('temp/' . Str::random(10));
                Storage::disk($disk)->makeDirectory($tempDir);

                $existingThumbnail = [];
                $media = $this->record->getMedia(Article::MEDIA_COLLECTION_NAME)->where('custom_properties.is_cover', true)->first();

                if ($media) {
                    $tempFilePath = $tempDir . '/' . $media->file_name;
                    if (Storage::disk($disk)->exists($media->getPath())) {
                        $fileContents = Storage::disk($disk)->get($media->getPath());
                        Storage::disk($disk)->put($tempFilePath, $fileContents);
                        $existingThumbnail[$media->file_name] = [
                            'path' => $tempFilePath,
                            'custom_properties' => $media->custom_properties,
                        ];
                    } else {
                        Log::warning('Existing file not found: ', ['file' => $media->getPath()]);
                    }
                }

                $file = $data['video_thumbnail'];

                // Check if the file exists in the temporary directory or on the storage disk
                if (isset($existingThumbnail[$file])) {
                    $filePath = $existingThumbnail[$file]['path'];
                    $customProperties = array_merge($existingThumbnail[$file]['custom_properties'], ['is_cover' => true]);
                } elseif (Storage::disk($disk)->exists($file)) {
                    $filePath = Storage::disk($disk)->path($file);
                    $customProperties = ['is_cover' => true];
                } else {
                    Log::warning('File not found: ', ['file' => $file]);
                    return $data;
                }

                $media = $article->addMediaFromDisk($data['video_thumbnail'])
                    ->withCustomProperties(['is_cover' => true])
                    ->toMediaCollection(Article::MEDIA_COLLECTION_NAME, $disk);

                Log::info('File uploaded: ', ['media' => $media, 'file' => $file]);

                // Delete the file from the temporary directory if it exists
                if (isset($existingThumbnail[$file])) {
                    Storage::disk($disk)->delete($existingThumbnail[$file]['path']);
                }

                $media->save();
                Storage::deleteDirectory($tempDir);
            }

            // Handle Video
            if (isset($data['video'])) {
                $disk = config('filesystems.default');
                if ($disk == 's3') {
                    $disk = 's3_public';
                }

                Log::info('Video: ', ['video' => $data['video']]);

                // Save existing video media to a temporary directory
                $tempDir = Storage::disk($disk)->path('temp/' . Str::random(10));
                Storage::disk($disk)->makeDirectory($tempDir);

                $existingVideo = [];
                $media = $this->record->getMedia(Article::MEDIA_COLLECTION_NAME)->where('custom_properties.is_cover', false)->first();

                if ($media) {
                    $tempFilePath = $tempDir . '/' . $media->file_name;
                    if (Storage::disk($disk)->exists($media->getPath())) {
                        $fileContents = Storage::disk($disk)->get($media->getPath());
                        Storage::disk($disk)->put($tempFilePath, $fileContents);
                        $existingVideo[$media->file_name] = [
                            'path' => $tempFilePath,
                            'custom_properties' => $media->custom_properties,
                        ];
                    } else {
                        Log::warning('Existing file not found: ', ['file' => $media->getPath()]);
                    }
                }

                $file = $data['video'];

                // Check if the file exists in the temporary directory or on the storage disk
                if (isset($existingVideo[$file])) {
                    $filePath = $existingVideo[$file]['path'];
                    $customProperties = array_merge($existingVideo[$file]['custom_properties'], ['is_cover' => false]);
                } elseif (Storage::disk($disk)->exists($file)) {
                    $filePath = Storage::disk($disk)->path($file);
                    $customProperties = ['is_cover' => false];
                } else {
                    Log::warning('File not found: ', ['file' => $file]);
                    return $data;
                }

                $media = $article->addMediaFromDisk($data['video'])
                    ->withCustomProperties(['is_cover' => false])
                    ->toMediaCollection(Article::MEDIA_COLLECTION_NAME, $disk);

                Log::info('File uploaded: ', ['media' => $media, 'file' => $file]);

                // Delete the file from the temporary directory if it exists
                if (isset($existingVideo[$file])) {
                    Storage::disk($disk)->delete($existingVideo[$file]['path']);
                }

                $media->save();
                Storage::deleteDirectory($tempDir);

            }
            $this->record->clearMediaCollection(Article::MEDIA_COLLECTION_NAME);
            return $data;
        }
        return $data;
    }

    protected function afterSave(): void
    {
        $article = $this->record;

        // Dispatch the job for the attached tags
        $article->tags->each(function ($tag) {
            Log::info('Dispatching job for tag: ' . $tag->name);
            UpdateArticleTagArticlesCount::dispatch($tag);
        });

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
                Log::info('Dispatching job for hashtag');
                UpdateArticleTagArticlesCount::dispatch($tag);
            });
        }

        // get article updated tags and final fire the job to get latest count
        $updated_article_tags = $article->tags()->get();
        foreach($updated_article_tags as $tag) {
            Log::info('final fire for tags');
            UpdateArticleTagArticlesCount::dispatch($tag);
        }

        if (isset($this->record)) {
            // trigger searcheable to reindex
            $this->record->searchable();
        }
    }
}
