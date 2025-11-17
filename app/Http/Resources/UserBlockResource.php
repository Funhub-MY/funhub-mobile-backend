<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Illuminate\Http\Resources\Json\JsonResource;

class UserBlockResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
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
