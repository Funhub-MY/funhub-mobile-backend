<?php

namespace App\Http\Resources;

use App\Models\Interaction;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

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
        $location = null;
        if ($this->has('location')) {
            $loc = $this->location->first();

            // if artilce locaiton has ratings, get current article owner's ratings
            if ($loc && $loc->has('ratings')) {
                $articleOwnerRating = $loc->ratings->where('user_id', $this->user->id)->first();
                $location = [
                    'id' => $loc->id,
                    'name' => $loc->name,
                    'address' => $loc->full_address,
                    'lat' => floatval($loc->lat),
                    'lng' => floatval($loc->lng),
                    'rated_count' => $loc->ratings->count(),
                ];
            }
        }

		// Process highlight messages
		$highlight_messages = null;
		if ($this->highlight_messages) {
			$messages = is_string($this->highlight_messages) ?
				json_decode($this->highlight_messages, true) :
				$this->highlight_messages;

			// Filter out null messages and keep only valid ones
			$validMessages = array_filter($messages, function($message) {
				return isset($message['message']) && $message['message'] !== null;
			});

			// If we have valid messages, return them, otherwise return null
			if (!empty($validMessages)) {
				$highlight_messages = array_values($validMessages); // Reset array keys
			}
		}

        // horizontal banner
        $horizontalMedia = $this->getFirstMedia(MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER);
        $verticalBanner = $this->getFirstMedia(MerchantOffer::MEDIA_COLLECTION_NAME);

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'merchant_id' => ($this->user && $this->user->merchant) ? $this->user->merchant->id : null,
            // to deprecate. use stores instead
            'store' => [
                'id' => ($this->store) ? $this->store->id : null,
                'name' => ($this->store) ? $this->store->name : null,
            ],
            'stores' => ($this->stores) ? $this->stores->map(function ($store) {
                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'address' => $store->address,
                    'ratings' => number_format(floatval($store->storeRatings->avg('rating')), 1),
                    'total_ratings' => ($store->storeRatings) ? $store->storeRatings->count() : 0,
                    'address_postcode' => $store->address_postcode,
                    'lat' => ($store->location && isset($store->location->lat)) ? floatval($store->location->lat) : $store->lang,
                    'lng' => ($store->location && isset($store->location->lng)) ? floatval($store->location->lng) : $store->long,

                    'distance' => (float) $store->distance, //Kenneth - add the distance for stores
                    'is_hq' => $store->is_hq,
                    'state' => $store->state,
                    'country' => $store->country
                ];
            }) : null,
           'merchant' => [
                'id' => ($this->user && $this->user->merchant) ? $this->user->merchant->id : null,
                'logo' => ($this->user && $this->user->merchant && $this->user->merchant->media->count() > 0) ? $this->user->merchant->media->filter(function ($media) {
                    return $media->collection_name == Merchant::MEDIA_COLLECTION_NAME;
                })->first()->original_url : null,
                'brand_name' => ($this->user && $this->user->merchant) ? $this->user->merchant->brand_name : null,
                'business_name' => ($this->user && $this->user->merchant) ? $this->user->merchant->business_name : null,
                'business_phone_no' => ($this->user && $this->user->merchant) ? $this->user->merchant->business_phone_no : null,
                'user' => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ]
            ],
            'name' => $this->name,
            'is_flash' => $this->flash_deal,
			'highlight_messages' => $highlight_messages,
			'description' => $this->description,
            'fine_print' => $this->fine_print,
            'redemption_policy' => $this->redemption_policy,
            'cancellation_policy' => $this->cancellation_policy,
            'point_cost' => floatval($this->unit_price),
            'point_fiat_price' => floatval($this->point_fiat_price),
            'available_points_to_discount' => $this->when(isset($this->available_points_to_discount), floatval($this->available_points_to_discount)),
            'price_after_discount_with_points' => $this->when(isset($this->price_after_discount_with_points), floatval($this->price_after_discount_with_points)),
            'discounted_point_fiat_price' => floatval($this->discounted_point_fiat_price),
            'fiat_price' => floatval($this->fiat_price),
            'discounted_fiat_price' => floatval($this->discounted_fiat_price),
            'default_purchase_method' => $this->purchase_method,
            'available_at' => $this->available_at,
            'available_until' => $this->available_until,
            'expiry_days' => $this->expiry_days,
            'quantity' => $this->unclaimed_vouchers_count,
            'claimed_quantity' => ($this->claims) ? $this->claims->filter(function ($q) {
                return $q->pivot->status == MerchantOffer::CLAIM_SUCCESS;
            })->count() : 0,
            'media' => MediaResource::collection($this->media),
            'gallery' => ($this->media) ? MediaResource::collection($this->media->filter(function ($item) {
                return $item->collection_name != MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER;
            })) : null,
            'horizontal_banner' => ($horizontalMedia) ? new MediaResource($horizontalMedia) : null,
            'vertical_banner' => ($verticalBanner) ? new MediaResource($verticalBanner) : null,
            'interactions' => InteractionResource::collection($this->interactions),
            'location' => $location,
            'count' => [
                'likes' => $this->interactions->where('type', Interaction::TYPE_LIKE)->count(),
                'share' => $this->interactions->where('type', Interaction::TYPE_SHARE)->count(),
                'bookmarks' => $this->interactions->where('type', Interaction::TYPE_BOOKMARK)->count(),
                'views' => $this->views->count()
            ],
            'my_interactions' => [
                'like' => $this->whenLoaded('likes', function () {
                    return $this->likes->first();
                }),
                'share' => $this->whenLoaded('interactions', function () {
                    return $this->interactions->where('type', Interaction::TYPE_SHARE)->first();
                }),
                'bookmark' => $this->whenLoaded('interactions', function () {
                    return $this->interactions->where('type', Interaction::TYPE_BOOKMARK)->first();
                }),
            ],
            'user_liked' => $this->whenLoaded('likes', function () {
                return $this->likes->isNotEmpty();
            }, false),
            'user_bookmarked' => $this->whenLoaded('interactions', function () {
                return $this->interactions->where('type', Interaction::TYPE_BOOKMARK)->isNotEmpty();
            }, false),
            'user_purchased_before_from_merchant' => (isset($this->user_purchased_before_from_merchant)) ? $this->user_purchased_before_from_merchant : false,
            'categories' => MerchantCategoryResource::collection($this->categories),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_diff' => $this->created_at->diffForHumans(),
            'updated_at_diff' => $this->updated_at->diffForHumans(),
        ];
    }
}
