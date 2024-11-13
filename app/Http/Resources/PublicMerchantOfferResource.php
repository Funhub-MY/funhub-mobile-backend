<?php

namespace App\Http\Resources;

use App\Models\Interaction;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicMerchantOfferResource extends JsonResource
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

        // horizontal banner
        $horizontalMedia = $this->getFirstMedia(MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER);
        $verticalBanner = $this->getFirstMedia(MerchantOffer::MEDIA_COLLECTION_NAME);

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'merchant_id' => ($this->user && $this->user->merchant) ? $this->user->merchant->id : null,
            'store' => [
                'id' => ($this->store) ? $this->store->id : null,
                'name' => ($this->store) ? $this->store->name : null,
            ],
            'logo' => ($this->getFirstMediaUrl(Merchant::MEDIA_COLLECTION_NAME)) ? $this->getFirstMediaUrl(Merchant::MEDIA_COLLECTION_NAME) : null,
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
            'description' => $this->description,
            'fine_print' => $this->fine_print,
            'redemption_policy' => $this->redemption_policy,
            'cancellation_policy' => $this->cancellation_policy,
            'point_cost' => floatval($this->unit_price),
            'point_fiat_price' => floatval($this->point_fiat_price),
            'discounted_point_fiat_price' => floatval($this->discounted_point_fiat_price),
            'fiat_price' => floatval($this->fiat_price),
            'discounted_fiat_price' => floatval($this->discounted_fiat_price),
            'default_purchase_method' => $this->purchase_method,
            'available_at' => $this->available_at,
            'available_until' => $this->available_until,
            'expiry_days' => $this->expiry_days,
            'quantity' => $this->quantity,
            // 'claimed_quantity' => $this->claimed_quantity,
            'media' => MediaResource::collection($this->media),
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
            'categories' => MerchantCategoryResource::collection($this->categories),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_diff' => $this->created_at->diffForHumans(),
            'updated_at_diff' => $this->updated_at->diffForHumans(),
        ];
    }
}
