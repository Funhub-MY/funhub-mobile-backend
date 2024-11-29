<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SyncMerchantCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // // Get the language from the request header
        // $locale = config('app.locale');
        // if ($request->header('X-Locale')) {
        //     $locale = $request->header('X-Locale');
        // }

        // $translatedName = $this->name;
        // if (isset($this->name_translation)) {
        //     // Get the translated names
        //     $translatedNames = json_decode($this->name_translation, true);

        //     // Check if $translatedNames is null
        //     if ($translatedNames) {
        //         // Check if $locale exists in the $translatedNames
        //         if (isset($translatedNames[$locale])) {
        //             // Get the translation based on locale from header
        //             $translatedName = $translatedNames[$locale];
        //         } else {
        //             // Get the translation based on default locale
        //             $defaultLocale = config('app.locale');
        //             $translatedName = $translatedNames[$defaultLocale];
        //         }
        //     }
        // }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_translation' => $this->name_translation,
            'slug' => $this->slug,
            'parent_id' => $this->parent_id,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
