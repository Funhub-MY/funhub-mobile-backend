<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use App\Models\Merchant;
use Illuminate\Http\Resources\Json\JsonResource;

class SyncMerchantCampaignResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'offers' => $this->merchantOffers->map(function ($offer) {
                return [
                    'id' => $offer->id,
                    'name' => $offer->name,
                    'sku' => $offer->sku,
                    'name_sku' => $offer->name_sku,
                    'description' => $offer->description,
                    'status' => $offer->status,
                    'vouchers' => count($offer->vouchers),
                    'redeemd' => count($offer->redeems),
                    'claims' => count($offer->claims),
                    'unclaimedVouchers' => count($offer->unclaimedVouchers)
                ];
            }),


        ];
    }
}
