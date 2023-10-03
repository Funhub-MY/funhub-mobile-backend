<?php

namespace App\Http\Resources;

use App\Models\SupportRequestMessage;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportRequestMessageResource extends JsonResource
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
            'user' => new UserResource($this->user),
            'message' => $this->message,
            'is_self' => $this->user_id === auth()->id(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'media' => MediaResource::collection($this->getMedia(SupportRequestMessage::MEDIA_COLLECTION_NAME)),
            'created_at_for_humans' => $this->created_at->diffForHumans(),
            'updated_at_for_humans' => $this->updated_at->diffForHumans(),
        ];
    }
}
