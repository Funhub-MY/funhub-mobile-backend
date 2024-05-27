<?php

namespace App\Http\Resources;

use App\Filament\Resources\MerchantResource;
use App\Models\Interaction;
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
        $bookmark_interaction_id = null;
        if (auth()->user()) {
            $interaction = $this->interactions->where('type', Interaction::TYPE_BOOKMARK)->where('user_id', auth()->user()->id)->first();
            if ($interaction) {
                $bookmark_interaction_id = $interaction->id;
            }
        }

        if ($this->location) {
            $totalRatings = $this->store_ratings_count + $this->location()->ratings()->count();
            $averageRating = ($totalRatings > 0) ? ($this->storeRatings->sum('rating') + $this->location->ratings->sum('rating')) / $totalRatings : 0;
        } else {
            $totalRatings = $this->store_ratings_count;
            $averageRating = ($totalRatings > 0) ? $this->storeRatings->sum('rating') / $totalRatings : 0;
        }

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
            'business_hours' => ($this->business_hours) ? json_decode($this->business_hours) : null,
            'location' => $this->location,
            'address' => $this->address,
            'address_postcode' => $this->address_postcode,
            'categories' => MerchantCategoryResource::collection($this->categories),
            'ratings' => number_format(floatval($averageRating), 1),
            'total_ratings' => $totalRatings,
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
            'has_merchant_offers' => ($this->available_merchant_offers_count > 0) ? true : false, // from relation availableMerchantOffers
            'user_bookmarked' => (auth()->user()) ? $this->interactions->where('type', Interaction::TYPE_BOOKMARK)->where('user_id', auth()->user()->id)->count() > 0 : false,
            'bookmark_interaction_id' => $bookmark_interaction_id,
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
