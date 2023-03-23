<?php

namespace App\Http\Resources;

use App\Models\Interaction;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
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
            'slug' => $this->slug,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'categories' => ArticleCategoryResource::collection($this->categories),
            'media' => $this->media,
            'tags' => $this->tags,
            'comments' => CommentResource::collection($this->comments),
            'interactions' => InteractionResource::collection($this->interactions),
            'count' => [
                'comments' => $this->comments_count,
                'likes' => $this->interactions->where('type', Interaction::TYPE_LIKE)->count(),
                'dislikes' => $this->interactions->where('type', Interaction::TYPE_DISLIKE)->count(),
                'share' => $this->interactions->where('type', Interaction::TYPE_SHARE)->count(),
                'bookmarks' => $this->interactions->where('type', Interaction::TYPE_BOOKMARK)->count(),
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_diff' => $this->created_at->diffForHumans(),
            'updated_at_diff' => $this->updated_at->diffForHumans(),
        ];
    }
}
