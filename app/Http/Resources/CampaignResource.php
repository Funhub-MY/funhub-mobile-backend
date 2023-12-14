<?php

namespace App\Http\Resources;

use App\Models\Campaign;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
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
            'url' => $this->url,
            'description' => $this->description,
            'banner' => $this->getFirstMediaUrl(Campaign::BANNER_COLLECTION),
            'icon' => $this->getFirstMediaUrl(Campaign::ICON_COLLECTION),
            'active_questions' => CampaignQuestionResource::collection($this->activeQuestionsByBrand),
        ];
    }
}
