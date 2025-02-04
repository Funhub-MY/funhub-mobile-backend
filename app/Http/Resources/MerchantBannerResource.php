<?php

namespace App\Http\Resources;

use App\Models\MerchantBanner;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantBannerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'link_to' => $this->link_to,
            'banner_url' => $this->getFirstMediaUrl(MerchantBanner::MEDIA_COLLECTION_NAME),
            'created_at' => $this->created_at
        ];
    }
}
