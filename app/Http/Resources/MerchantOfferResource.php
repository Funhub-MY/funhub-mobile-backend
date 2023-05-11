<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MerchantOfferResource extends JsonResource
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
            'sku' => $this->sku,
            'merchant_id' => ($this->user) ? $this->user->merchant->id : null,
            'store' => [
                'id' => ($this->store) ? $this->store->id : null,
                'name' => ($this->store) ? $this->store->name : null,
            ],
            'merchant' => [
                'id' => ($this->user) ? $this->user->merchant->id : null,
                'business_name' => ($this->user) ? $this->user->merchant->business_name : null,
                'user' => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ],
            ],
            'name' => $this->name,
            'description' => $this->description,
            'unit_price' => $this->unit_price,
            'available_at' => $this->available_at,
            'available_until' => $this->available_until,
            'quantity' => $this->quantity,
            'claimed_quantity' => $this->claimed_quantity,
            'media' => MediaResource::collection($this->media),
            'interactions' => InteractionResource::collection($this->interactions),
            'categories' => MerchantCategoryResource::collection($this->categories),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_diff' => $this->created_at->diffForHumans(),
            'updated_at_diff' => $this->updated_at->diffForHumans(),
        ];
    }
}
