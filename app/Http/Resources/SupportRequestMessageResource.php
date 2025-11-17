<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use App\Models\SupportRequestMessage;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportRequestMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
		$mediaCollection = $this->getMedia(SupportRequestMessage::MEDIA_COLLECTION_NAME);

        return [
            'id' => $this->id,
            'user' => new UserResource($this->user),
            'message' => $this->message,
            'is_self' => $this->user_id === auth()->id(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
			'media' => MediaResource::collection($mediaCollection)->map(function ($media) use ($request) {
				$baseData = $media->toArray($request);
				$baseData['type'] = str_starts_with($media->mime_type, 'video') ? 'video' :
					(str_starts_with($media->mime_type, 'image') ? 'image' : 'unknown');
				return $baseData;
			}),
			'created_at_for_humans' => $this->created_at->diffForHumans(),
            'updated_at_for_humans' => $this->updated_at->diffForHumans(),
        ];
    }
}
