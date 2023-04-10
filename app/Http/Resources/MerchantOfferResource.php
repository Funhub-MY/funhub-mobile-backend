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
            'merchant_id' => $this->merchant_id,
            'store' => [
                'id' => $this->merchant->store->id,
                'name' => $this->merchant->store->name,
            ],
            'merchant' => [
                'id' => $this->merchant->id,
                'business_name' => $this->merchant->business_name,
                'user' => [
                    'id' => $this->merchant->user->id,
                    'name' => $this->merchant->user->name,
                ],
            ],
            'name' => $this->name,
            'description' => $this->description,
            'unit_price' => $this->unit_price,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'is_deleted' => $this->is_deleted,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ]; 
    }
}
