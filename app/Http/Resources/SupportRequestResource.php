<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SupportRequestResource extends JsonResource
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
            'category' => new SupportRequestCategoryResource($this->category),
            'title' => $this->title,
            'status' => $this->status,
            'requestor' => new UserResource($this->requestor),
            'latest_message' => ($this->messages->count() > 0) ? $this->messages->last()->message : null,
            'assignee' => [
                'id' => $this->assignee_id,
                'name' => $this->assignee ? $this->assignee->name : null,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
