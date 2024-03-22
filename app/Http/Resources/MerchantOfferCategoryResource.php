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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'slug' => $this->slug,
            'icon' => null,
            'cover_media_id' => $this->cover_media_id,
            'is_child' => ($this->parent_id) ?? false,
            'parent' => ($this->parent_id) ? new MerchantOfferCategoryResource($this->parent) : null,
            'is_featured' => $this->is_featured,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_diff' => $this->created_at->diffForHumans(),
            'updated_at_diff' => $this->updated_at->diffForHumans(),
            'is_active' => $this->is_active,
        ];
    }
}
