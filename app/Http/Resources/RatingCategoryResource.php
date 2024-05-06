<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RatingCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $locale = config('app.locale');
        if ($request->header('X-Locale')) {
            $locale = $request->header('X-Locale');
        }

        // Get the translated names
         $translatedNames = json_decode($this->name_translations, true);

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
            'ratings_count' => ($this->store_ratings_count) ? $this->store_ratings_count : 0,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
