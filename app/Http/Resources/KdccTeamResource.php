<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class KdccTeamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category_id' => $this->category_id,
            'vote_count' => $this->vote_count,
            'team_image_path' => asset('storage/' . $this->team_image_path),
            'image_url' => $this->image_url,
            
            // Include user's vote status if requested
            'user_has_voted' => $this->user_has_voted,
            
            // Include rank if available
            'rank' => $this->when(isset($this->rank), $this->rank),
        ];
    }
}
