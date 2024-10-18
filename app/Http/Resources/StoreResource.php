<?php

namespace App\Http\Resources;

use App\Models\Interaction;
use App\Models\Merchant;
use App\Models\Store;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\MerchantResource;
use App\Models\Article;

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

        $merchant = null;
        // $logo = null;
        $photos = [];
        // get from first latest article media
        if ($this->articles) {
            $firstArticle = $this->articles->first();
            if ($firstArticle) {
                $articlePhoto = $firstArticle->media->filter(function ($media) {
                    return str_contains($media->mime_type, 'image');
                })->first();
                $photos = ($articlePhoto) ? [$articlePhoto->getFullUrl()] : null;
            }
        }

        // if not articles then get from store photos
        if (empty($photos) || count($photos) == 0) {
            $photos = $this->media->filter(function ($item) {
                return $item->collection_name == Store::MEDIA_COLLECTION_PHOTOS;
            })->map(function ($item) {
                return $item->getFullUrl();
            });
        }

        $currentDayBusinessHour = null;
        if ($this->business_hours) {
            // today day in number, eg, Monday = 1, Sunday = 7
            $today = date('N');
            $buisnessHours = json_decode($this->business_hours, true);

            if (isset($buisnessHours[$today])) {
            $currentDayBusinessHour = $buisnessHours[$today];
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'manager_name' => $this->manager_name,
            'onboarded' => ($this->merchant) ? true : false,
            // get merchant company logo
            'logo' => ($this->merchant) ? $this->merchant->getFirstMediaUrl(Merchant::MEDIA_COLLECTION_NAME) : null,
            'photos' => $photos,
            'merchant' => $merchant,
            'other_stores' => ($this->otherStores) ? $this->otherStores->map(function ($store) {
                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'address' => $store->address,
                    'address_postcode' => $store->address_postcode,
                    'business_hours' => ($store->business_hours) ? json_decode($store->business_hours) : null,
                ];
            }) : null,
            'business_phone_no' => $this->business_phone_no,
            'business_hours' => ($this->business_hours) ? json_decode($this->business_hours) : null,
            'current_day_business_hour' => ($this->business_hours) ? $currentDayBusinessHour : null,
            'rest_hours' => ($this->rest_hours) ? json_decode($this->rest_hours) : null,
            'location' => $this->location,
            'address' => $this->address,
            'address_postcode' => $this->address_postcode,
            'categories' => MerchantCategoryResource::collection($this->categories),
            'parent_category_ids' => $this->parentCategories->pluck('id'),
            'ratings' => number_format(floatval($this->ratings), 1),
            'total_ratings' => $this->store_ratings_count, // store rating already included cloned ratings from article->location ratings
            'total_article_ratings' => $this->location_ratings_count ?? 0,
            'total_articles_same_location' => ($this->articles) ? $this->articles->count() : 0,
            'followings_been_here' => [],
            // 'followings_been_here' => $this->articles->map(function ($article) {
            //     $user = $article->user;
            //     $isFollowing = $user->followers->contains('id', auth()->id());
            //     if ($isFollowing) {
            //         return [
            //             'id' => $user->id,
            //             'name' => $user->name,
            //             'username' => $user->username,
            //             'avatar' => $user->avatar_url,
            //             'avatar_thumb' => $user->avatar_thumb_url,
            //             'has_avatar' => $user->hasMedia('avatar'),
            //         ];
            //     }
            // })->filter()->unique('id')->values(),
            'has_merchant_offers' => ($this->available_merchant_offers_count > 0) ? true : false, // from relation availableMerchantOffers
            'user_bookmarked' => (auth()->user()) ? $this->interactions->where('type', Interaction::TYPE_BOOKMARK)->where('user_id', auth()->user()->id)->count() > 0 : false,
            'bookmark_interaction_id' => $bookmark_interaction_id,
            'lang' => $this->lang,
            'long' => $this->long,
            'is_hq' => $this->is_hq,
            'is_closed' => $this->is_closed,
            'user_id' => $this->user_id,
            'state_id' => $this->state_id,
            'country_id' => $this->country_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
