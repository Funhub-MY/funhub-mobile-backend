<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class KdccVoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'category_id' => $this->category_id,
            'team_id' => $this->team_id,
            'voted_at' => $this->created_at->format('Y-m-d H:i:s'),
            
            'team' => [
                'id' => $this->team->id,
                'name' => $this->team->name,
                'category_id' => $this->team->category_id,
                'vote_count' => $this->team->vote_count,
                'image_url' => $this->team->image_url,
            ],
        ];
    }
}
