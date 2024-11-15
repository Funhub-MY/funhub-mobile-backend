<?php

namespace App\Http\Resources;

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
                ];
            });
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'unit_price' => $this->unit_price,
            'discount_price' => $this->discount_price,
            'unlimited_supply' => $this->unlimited_supply,
            'quantity' => $this->quantity,
            'reward' => ($rewards) ? $rewards->toArray() : null,
            'status' => $this->status,
            'thumbnail' => $this->thumbnail,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
