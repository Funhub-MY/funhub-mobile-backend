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
            'email' => $this->email,
            'auth_provider' => $this->auth_provider,
            'avatar' => $this->avatar_url,
            'avatar_thumb' => $this->avatar_thumb_url,
            'bio' => $this->bio,
            'cover' => $this->cover_url,
            'articles_published_count' => $this->articles()->published()->count(),
            'following_count' => $this->followings()->count(),
            'followers_count' => $this->followers()->count(),
            'has_completed_profile' => $this->has_completed_profile,
            'has_avatar' => $this->hasMedia('avatar'),
            'point_balance' => $this->point_balance,
            'unread_notifications_count' => $this->unreadNotifications()->count(),
            'is_following' => ($request->user()) ? $this->resource->followers->contains($request->user()->id) : false
        ];
    }
}
