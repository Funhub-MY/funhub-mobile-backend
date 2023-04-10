<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;

class CommentResource extends JsonResource
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
            'commentable_id' => $this->commentable_id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => $this->user->avatar_url,
            ],
            'counts' => [
                'likes' => $this->likes->count(),
                'replies' => $this->replies->count(),
            ],
            'body' => $this->body,
            'liked_by_user' => $this->likes->contains('user_id', auth()->id()),
            'likes' => CommentLikeResource::collection($this->likes),
            'replies' => CommentResource::collection($this->replies),
            'is_reply' => $this->parent_id ? true : false,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_diff' => $this->created_at->diffForHumans(),
            'updated_at_diff' => $this->created_at->diffForHumans(),
        ];
    }
}
