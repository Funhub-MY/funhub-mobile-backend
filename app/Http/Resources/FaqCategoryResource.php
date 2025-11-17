<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Illuminate\Http\Resources\Json\JsonResource;

class FaqCategoryResource extends JsonResource
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
            'icon' => new MediaResource($this->getFirstMedia('icon')),
            'is_featured' => $this->is_featured,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
