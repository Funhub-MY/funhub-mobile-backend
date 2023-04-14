<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'avatar' => $this->avatar_url,
            'avatar_thumb' => $this->avatar_thumb_url,
            'following_count' => $this->followings()->count(),
            'followers_count' => $this->followers()->count(),
            'is_following' => (auth()->user()) ? auth()->user()->whereRelation('followings', 'following_id', $this->id)->exists() : false
        ];
    }
}
