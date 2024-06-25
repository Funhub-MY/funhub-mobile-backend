<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SupportRequestCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $locale = $request->header('X-Locale') ?? config('app.locale');
        $translatedNames = json_decode($this->name_translation, true);
        $translatedDescriptions = json_decode($this->description_translation, true);

        $translatedName = $translatedNames[$locale] ?? $this->name;
        $translatedDescription = $translatedDescriptions[$locale] ?? $this->description;

        return [
            'id' => $this->id,
            'name' => $translatedName,
            'description' => $translatedDescription,
            'type' => $this->type,
            'icon' => $this->icon,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
