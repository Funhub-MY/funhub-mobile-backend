<?php

namespace App\Http\Resources;

use App\Models\MerchantOffer;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantOfferClaimResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $hasExpired = false;
        $expiringAt = null;
        if ($this->merchantOffer->expiry_days && $this->merchantOffer->expiry_days > 0) {
            $hasExpired = Carbon::parse($this->created_at)->addDays($this->merchantOffer->expiry_days)->endOfDay()->isPast();
            $expiringAt = Carbon::parse($this->created_at)->addDays($this->merchantOffer->expiry_days)->endOfDay()->format('Y-m-d H:i:s');
        }

        $hasUserRated = false;
        if ($this->stores) {
            foreach ($this->stores as $store) {
                if ($store->storeRatings->where('user_id', $this->user->id)->exists()) {
                    $hasUserRated = true;
                    break;
                }
            }
        }

        return [
            'id' => $this->id,
            'order_no' => $this->order_no,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'voucher_code' => ($this->voucher) ? $this->voucher->code : null,
            'merchant_offer' => new MerchantOfferResource($this->merchantOffer),
            'unit_price' => $this->unit_price,
            'quantity' => $this->quantity,
            'discount' => $this->discount,
            'tax' => $this->tax,
            'total' => $this->total,
            'net_amount' => $this->net_amount,
            'purchase_method' => $this->purchase_method,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'redeemed' => ($this->redeem) ? true : false,
            'redeem' => ($this->redeem) ? $this->redeem : null,
            'has_expired' => $hasExpired,
            'has_user_rated' => $this->last_rated_at ? true : false,
            'last_rated_at' => $this->last_rated_at,
            'expiring_at' => $expiringAt,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_diff' => $this->created_at->diffForHumans(),
            'updated_at_diff' => $this->updated_at->diffForHumans(),
        ];
    }
}
