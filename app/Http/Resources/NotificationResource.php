<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
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
            'message' => $this->data['message'] ?? null,
            'object' => $this->data['object'] ?? null,
            'object_id' => $this->data['object_id'] ?? null,
            'link_to_url' => $this->data['link_to_url'] ?? null,
            'link_to_object' => $this->data['link_to_object'] ?? null,
            'action' => $this->data['action'] ?? null,
            'from_user' => [
                'id' => $this->from_user->id,
                'name' => $this->from_user->name,
                'username' => $this->from_user->username,
                'avatar' => $this->from_user->avatar_url,
                'avatar_thumb' => $this->from_user->avatar_thumb_url,
                'is_following' => $this->from_user->is_following
            ],
            'created_at_raw' => $this->created_at,
            'created_at' => $this->created_at->diffForHumans(),
        ];
    }
}
