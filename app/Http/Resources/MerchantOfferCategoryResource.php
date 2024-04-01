<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MerchantOfferCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // Get the language from the request header
        $locale = config('app.locale');
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
            'description' => $this->description,
            'slug' => $this->slug,
            'icon' => $this->getFirstMediaUrl('merchant_offer_category'),
            'cover_media_id' => $this->cover_media_id,
            'is_child' => ($this->parent_id) ?? false,
            'parent' => ($this->parent_id) ? new MerchantOfferCategoryResource($this->parent) : null,
            'is_featured' => $this->is_featured,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'no_of_offers' => ($this->merchant_offers_count) ? $this->merchant_offers_count : 0,
            'created_at_diff' => $this->created_at->diffForHumans(),
            'updated_at_diff' => $this->updated_at->diffForHumans(),
            'is_active' => $this->is_active,
        ];
    }
}
