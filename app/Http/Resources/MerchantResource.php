<?php

namespace App\Http\Resources;

use App\Models\Merchant;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantResource extends JsonResource
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
            'business_name' => $this->business_name,
            'logo' => ($this->getFirstMediaUrl(Merchant::MEDIA_COLLECTION_NAME)) ? $this->getFirstMediaUrl(Merchant::MEDIA_COLLECTION_NAME) : null,
            'photos' => $this->getMedia(Merchant::MEDIA_COLLECTION_NAME_PHOTOS)->map(function ($item) {
                return $item->getFullUrl();
            }),
            'menus' => $this->getMedia(Merchant::MEDIA_COLLECTION_MENUS)->map(function ($item) {
                return $item->getFullUrl();
            }),
            'address' => $this->address,
            'address_postcode' => $this->address_postcode,
            'state' => $this->state,
            'country' => $this->country,
            'status' => $this->status,
            'categories' => $this->categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'created_at' => $category->created_at,
                    'updated_at' => $category->updated_at,
                ];
            }),
            'ratings' => $this->ratings,
            'stores' => $this->stores->map(function ($store) {
                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'manager_name' => $store->manager_name,
                    'business_phone_no' => $store->business_phone_no,
                    'business_hours' => $store->business_hours,
                    'address' => $store->address,
                    'address_postcode' => $store->address_postcode,
                    'lat' => $store->lat,
                    'lng' => $store->lng,
                    'is_hq' => $store->is_hq,
                    'state' => $store->state,
                    'country' => $store->country,
                    'created_at' => $store->created_at,
                    'updated_at' => $store->updated_at,
                ];
            }),
            'created_at' => $this->created_at,
        ];
    }
}
