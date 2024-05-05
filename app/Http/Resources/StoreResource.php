<?php

namespace App\Http\Resources;

use App\Filament\Resources\MerchantResource;
use App\Models\Merchant;
use App\Models\Store;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
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
            'manager_name' => $this->manager_name,
            'photos' => $this->getMedia(Store::MEDIA_COLLECTION_PHOTOS)->map(function ($item) {
                return $item->getFullUrl();
            }),
            'merchant' => new MerchantResource($this->merchant),
            'business_phone_no' => $this->business_phone_no,
            'business_hours' => ($this->business_hours) ? json_decode($this->business_hours, true) : null,
            'location' => $this->location,
            'address' => $this->address,
            'address_postcode' => $this->address_postcode,
            'categories' => MerchantCategoryResource::collection($this->categories),
            'ratings' => $this->ratings,
            'total_ratings' => $this->store_ratings_count,
            'lang' => $this->lang,
            'long' => $this->long,
            'is_hq' => $this->is_hq,
            'user_id' => $this->user_id,
            'state_id' => $this->state_id,
            'country_id' => $this->country_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
