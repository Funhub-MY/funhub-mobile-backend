<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use App\Models\Campaign;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
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
            'url' => $this->url,
            'description' => $this->description,
            'banner' => $this->getFirstMediaUrl(Campaign::BANNER_COLLECTION) ?? null,
            'icon' => $this->getFirstMediaUrl(Campaign::ICON_COLLECTION) ?? null,
            'event_banner' => $this->getFirstMediaUrl(Campaign::EVENT_COLLECTION) ?? null,
        ];
    }
}
