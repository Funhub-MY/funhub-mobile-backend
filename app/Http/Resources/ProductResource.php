<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // map rewards
        $rewards = null;
        if ($this->rewards) {
            $rewards = collect($this->rewards)->map(function ($reward) {
                return [
                    'id' => $reward->id,
                    'name' => $reward->name,
                    'description' => $reward->description,
                    'thumbnail' => $reward->thumbnail_url,
					'quantity' => $reward->pivot->quantity,
				];
            });
        }

        return [
            'id' => $this->id,
			'order' => $this->order,
            'type' => $this->type,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'campaign_url' => $this->campaign_url ?? null,
            'unit_price' => $this->unit_price,
            'discount_price' => $this->discount_price,
            'unlimited_supply' => $this->unlimited_supply,
            'quantity' => $this->quantity,
            'reward' => ($rewards) ? $rewards->toArray() : null,
            'status' => $this->status,
            'thumbnail' => $this->thumbnail,
            'background' => $this->getFirstMediaUrl(Product::MEDIA_BG_COLLECTION_NAME) ?? null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
