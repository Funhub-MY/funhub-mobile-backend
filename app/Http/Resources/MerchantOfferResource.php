<?php

namespace App\Http\Resources;

use App\Models\Interaction;
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

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'merchant_id' => ($this->user) ? $this->user->merchant->id : null,
            'store' => [
                'id' => ($this->store) ? $this->store->id : null,
                'name' => ($this->store) ? $this->store->name : null,
            ],
            'merchant' => [
                'id' => ($this->user) ? $this->user->merchant->id : null,
                'business_name' => ($this->user) ? $this->user->merchant->business_name : null,
                'business_phone_no' => ($this->user) ? $this->user->merchant->business_phone_no : null,
                'user' => new UserResource($this->user),
            ],
            'name' => $this->name,
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
            'claimed_quantity' => $this->claimed_quantity,
            'media' => MediaResource::collection($this->media),
            'interactions' => InteractionResource::collection($this->interactions),
            'location' => $location,
            'count' => [
                'likes' => $this->interactions->where('type', Interaction::TYPE_LIKE)->count(),
                'share' => $this->interactions->where('type', Interaction::TYPE_SHARE)->count(),
                'bookmarks' => $this->interactions->where('type', Interaction::TYPE_BOOKMARK)->count(),
                'views' => $this->views->count()
            ],
            'my_interactions' => [
                'like' => $this->interactions->where('type', Interaction::TYPE_LIKE)->where('user_id', auth()->user()->id)->first(),
                'share' => $this->interactions->where('type', Interaction::TYPE_SHARE)->where('user_id', auth()->user()->id)->first(),
                'bookmark' => $this->interactions->where('type', Interaction::TYPE_BOOKMARK)->where('user_id', auth()->user()->id)->first(),
            ],
            'user_liked' => (auth()->user()) ? $this->likes()->where('user_id', auth()->user()->id)->exists() : false,
            'user_bookmarked' => (auth()->user()) ? $this->interactions()->where('user_id', auth()->user()->id)->where('type', Interaction::TYPE_BOOKMARK)->exists() : false,
            'categories' => MerchantCategoryResource::collection($this->categories),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_diff' => $this->created_at->diffForHumans(),
            'updated_at_diff' => $this->updated_at->diffForHumans(),
        ];
    }
}
