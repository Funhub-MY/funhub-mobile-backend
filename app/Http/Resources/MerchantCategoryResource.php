<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        // Get the language from the request header
        $locale = config('app.locale');
        if ($request->header('X-Locale')) {
            $locale = $request->header('X-Locale');
        }

        $translatedName = $this->name;
        if (isset($this->name_translation)) {
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
            }
        }

        return [
            'id' => $this->id,
            'name' => $translatedName,
            'slug' => $this->slug,
            'parent_id' => $this->parent_id,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
