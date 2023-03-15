<?php

namespace App\Http\Resources;

use App\Models\Article;
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
            'categories' => $this->categories,
            'media' => $this->media,
            'tags' => $this->tags,
            'comments' => CommentResource::collection($this->comments),
            'interactions' => InteractionResource::collection($this->interactions),
            'count' => [
                'comments' => $this->comments->count(),
                'likes' => $this->interactions->where('type', Article::TYPE[0])->count(),
                'dislikes' => $this->interactions->where('type', Article::TYPE[1])->count(),
                'share' => $this->interactions->where('type', Article::TYPE[2])->count(),
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_diff' => $this->created_at->diffForHumans(),
            'updated_at_diff' => $this->updated_at->diffForHumans(),
        ];
    }
}
