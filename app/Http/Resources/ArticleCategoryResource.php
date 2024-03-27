<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ArticleCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $user = auth()->user();
        $is_interested = false;
        if ($user) {
            $is_interested = cache()->remember('user_interest_' . $user->id . '_category_' . $this->id, 60, function () use ($user) {
                $user->load('articleCategoriesInterests');
                return $user->articleCategoriesInterests->contains('id', $this->id);
            });
        }

        // Get the language from the request header
        if ($request->header('X-Locale')) {
            $locale = $request->header('X-Locale');
        }

        // Get the translated names
        $translatedNames = json_decode($this->name_translation, true);

        // Check if $translatedNames is null
        if ($translatedNames) {
            // Check if $locale exists in the $translatedNames
            if (isset($translatedNames[$locale])) {
                // Get the translation based on locale from header
                $translatedName = $translatedNames[$locale];
            } else {
                // Get the translation based on default locale
                $defaultLocale = config('app.locale');
                $translatedName = $translatedNames[$defaultLocale];
            }
        } else {
            // Fallback to the default name if translation doesn't exist
            $translatedName = $this->name;
        }

        return [
            'id' => $this->id,
            'name' => $translatedName,
            'name_translation' => $this->name_translation,
            'slug' => $this->slug,
            'icon' => $this->getFirstMediaUrl('article_category_icon'),
            'cover_media_id' => $this->cover_media_id,
            'is_child' => ($this->parent_id) ? true : false,
            'parent' => ($this->parent_id) ? new ArticleCategoryResource($this->parent) : null,
            'is_featured' => $this->is_featured,
            'is_interested' => $is_interested,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_diff' => $this->created_at->diffForHumans(),
            'updated_at_diff' => $this->updated_at->diffForHumans(),
        ];
    }
}
