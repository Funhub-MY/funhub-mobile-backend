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
            // get merchant company logo
            'logo' => ($this->merchant->getFirstMediaUrl(Merchant::MEDIA_COLLECTION_NAME)) ? $this->merchant->getFirstMediaUrl(Merchant::MEDIA_COLLECTION_NAME) : null,
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
            'total_articles_same_location' => $this->articles_count,
            'followings_been_here' => $this->whenLoaded('articles', function () {
                $uniqueUsers = $this->articles->pluck('user')
                    ->unique('id')
                    ->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'username' => $user->username,
                            'avatar' => $user->avatar_url,
                            'avatar_thumb' => $user->avatar_thumb_url,
                            'has_avatar' => $user->hasMedia('avatar'),
                        ];
                    });
                return $uniqueUsers;
            }),
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
