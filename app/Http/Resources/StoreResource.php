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

        Log::info('has article for stoere id' . $this->id, [
            'store_id' => $this->id,
            'articles' => $this->articles,
        ]);

        $merchant = null;
        $logo = null;
        $photos = [];
        if (!$this->merchant) {
            // not onboarded merchant do not have user_id so this will be null, manual populatew with just plain name
            $merchant = [
                'name' => $this->name,
            ];

            // get from first latest article media
            $firstArticles = $this->articles->first();
            if ($firstArticles) {
                $articlePhotos = $firstArticles->getMedia(Article::MEDIA_COLLECTION_NAME)->first();
                $photos = ($articlePhotos) ? $articlePhotos->getFullUrl() : null;
            }
        } else {
            $merchant = new MerchantResource($this->merchant);
            $logo = ($this->merchant->getFirstMediaUrl(Merchant::MEDIA_COLLECTION_NAME)) ? $this->merchant->getFirstMediaUrl(Merchant::MEDIA_COLLECTION_NAME) : null;
            $photos = $this->getMedia(Store::MEDIA_COLLECTION_PHOTOS)->map(function ($item) {
                return $item->getFullUrl();
            });
        }

        $currentDayBusinessHour = null;
        if ($this->business_hours) {
            // today day in number, eg, Monday = 1, Sunday = 7
            $today = date('N');
            $buisnessHours = json_decode($this->business_hours);

            foreach ($buisnessHours as $day => $businessHour) {
                if ($day == $today) {
                    $currentDayBusinessHour = $businessHour;
                    break;
                }
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'onboarded' => ($this->merchant) ? true : false,
            'manager_name' => $this->manager_name,
            // get merchant company logo
            'logo' => $logo,
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
            'location' => $this->location,
            'address' => $this->address,
            'address_postcode' => $this->address_postcode,
            'categories' => MerchantCategoryResource::collection($this->categories),
            'ratings' => number_format(floatval($this->ratings), 1),
            'total_ratings' => $this->store_ratings_count + $this->location_ratings_count,
            'total_article_ratings' => $this->location_ratings_count,
            'total_articles_same_location' => $this->articles_count,
            'followings_been_here' => $this->whenLoaded('articles', function () {
                $uniqueUsers = $this->articles->map(function ($article) {
                    $user = $article->user;
                    $isFollowing = $user->followers->contains('id', auth()->id());

                    Log::info('isFollowing: ' . $isFollowing, [
                        'article_owner' => $user->id,
                        'auth_id' => auth()->id(),
                        'followers' => $user->followers->pluck('id'),
                    ]);

                    if ($isFollowing) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'username' => $user->username,
                            'avatar' => $user->avatar_url,
                            'avatar_thumb' => $user->avatar_thumb_url,
                            'has_avatar' => $user->hasMedia('avatar'),
                        ];
                    }
                })->filter();

                return $uniqueUsers->unique('id')->values();
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
