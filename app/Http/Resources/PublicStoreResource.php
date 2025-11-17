<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use App\Models\Merchant;
use App\Models\Store;
use App\Models\Article;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicStoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        $bookmark_interaction_id = null;
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
                $photos = ($articlePhotos) ? [$articlePhotos->getFullUrl()] : null;
            }

            // if not articles then get from store photos
            if (empty($photos) || count($photos) == 0) {
                $photos = $this->media->map(function ($item) {
                    if ($item->collection_name == Store::MEDIA_COLLECTION_PHOTOS) {
                        return $item->getFullUrl();
                    }
                });
            }
        } else {
            $merchant = new MerchantResource($this->merchant);
            $logo = ($this->merchant->getFirstMediaUrl(Merchant::MEDIA_COLLECTION_NAME)) ? $this->merchant->getFirstMediaUrl(Merchant::MEDIA_COLLECTION_NAME) : null;
            $photos = $this->media->map(function ($item) {
                if ($item->collection_name == Store::MEDIA_COLLECTION_PHOTOS) {
                    return $item->getFullUrl();
                }
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
            'manager_name' => $this->manager_name,
            'onboarded' => ($this->merchant) ? true : false,
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
			'is_appointment_only' => $this->is_appointment_only,
            'business_hours' => ($this->business_hours) ? json_decode($this->business_hours) : null,
            'current_day_business_hour' => ($this->business_hours) ? $currentDayBusinessHour : null,
            'location' => $this->location,
            'address' => $this->address,
            'address_postcode' => $this->address_postcode,
            'categories' => MerchantCategoryResource::collection($this->categories),
            'parent_category_ids' => $this->parentCategories->pluck('id'),
            'ratings' => number_format(floatval($this->ratings), 1),
            'total_ratings' => $this->store_ratings_count + $this->location_ratings_count,
            'total_article_ratings' => $this->location_ratings_count,
            'total_articles_same_location' => $this->articles_count,
            'has_merchant_offers' => ($this->available_merchant_offers_count > 0) ? true : false, // from relation availableMerchantOffers
            'bookmark_interaction_id' => $bookmark_interaction_id,
            // other related data
            'articles' => PublicArticleResource::collection($this->articles),
            'store_ratings' => StoreRatingResource::collection($this->storeRatings),
            'merchant_offers' => PublicMerchantOfferResource::collection($this->merchant_offers),
            'lang' => $this->lang,
            'long' => $this->long,
            'is_hq' => $this->is_hq,
            // 'user_id' => $this->user_id,
            'state_id' => $this->state_id,
            'country_id' => $this->country_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
