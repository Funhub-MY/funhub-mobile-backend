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
        $data['sub_categories'] = $this->record->subCategories->pluck('id')->toArray();
        $data['locations'] = $this->record->location->pluck('id')->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    protected function afterSave(): void
    {
        $article = $this->record;

        $originalTags = $this->originalTags ?? [];
        $newTags = $article->tags->pluck('id')->toArray();
        $removedTags = array_diff($originalTags, $newTags);

        // Dispatch the job for the attached tags
        $article->tags->each(function ($tag) {
            Log::info('Dispatching job for tag: ' . $tag->name);
            UpdateArticleTagArticlesCount::dispatch($tag);
        });

        // Dispatch jobs for removed tags
        foreach ($removedTags as $tagId) {
            $tag = ArticleTag::find($tagId);
            if ($tag) {
                Log::info('Dispatching job for removed tag: ' . $tag->name);
                UpdateArticleTagArticlesCount::dispatch($tag);
            }
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

        // get article updated tags and final fire the job to get latest count
        $updated_article_tags = $article->tags()->get();
        foreach($updated_article_tags as $tag) {
            UpdateArticleTagArticlesCount::dispatch($tag);
        }
    }

}
