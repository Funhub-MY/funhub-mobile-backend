<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use App\Models\MerchantBanner;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantBannerResource extends JsonResource
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
            'title' => $this->title,
            'link_to' => $this->link_to,
            'banner_url' => $this->getFirstMediaUrl(MerchantBanner::MEDIA_COLLECTION_NAME),
            'order' => $this->order,
            'created_at' => $this->created_at
        ];
    }
}
