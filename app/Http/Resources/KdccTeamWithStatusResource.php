<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class KdccTeamWithStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array with vote status.
     */
    public function toArray($request)
    {
        $userId = auth()->user()->id;
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category_id' => $this->category_id,
            'vote_count' => $this->vote_count,
            'team_image_path' => $this->team_image_path,
            'image_url' => $this->image_url,
            'user_has_voted' => $userId ? $this->hasVotedBy($userId) : false,
            'rank' => $this->when(isset($this->rank), $this->rank),
        ];
    }
}
