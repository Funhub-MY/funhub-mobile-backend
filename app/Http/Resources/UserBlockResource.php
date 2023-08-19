<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserBlockResource extends JsonResource
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
            'id' => $this->blockable_id,
            'name' => $this->blockable->name,
            'username' => $this->blockable->username,
            'avatar' => $this->blockable->avatar_url,
            'avatar_thumb' => $this->blockable->avatar_thumb_url,
        ];
    }
}
