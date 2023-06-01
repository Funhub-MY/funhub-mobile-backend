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
            'from_user' => new UserResource($this->data['from_user']) ?? null,
            'is_read' => $this->read_at ? true : false,
            'created_at_raw' => $this->created_at,
            'created_at' => $this->created_at->diffForHumans(),
        ];
    }
}
