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
            $hasExpired = Carbon::parse($this->created_at)->addDays($this->merchantOffer->expiry_days)->isPast();
            $expiringAt = Carbon::parse($this->created_at)->addDays($this->merchantOffer->expiry_days)->format('Y-m-d H:i:s');
        }

        return [
            'id' => $this->id,
            'order_no' => $this->order_no,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
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
            'redeem' => $this->redeem,
            'has_expired' => $hasExpired,
            'expiring_at' => $expiringAt,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_diff' => $this->created_at->diffForHumans(),
            'updated_at_diff' => $this->updated_at->diffForHumans(),
        ];
    }
}
