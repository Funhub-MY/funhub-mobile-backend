<?php

namespace App\Http\Resources;

use App\Models\Article;
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
            'user' => new UserResource($this->user),
            'categories' => ArticleCategoryResource::collection($this->categories),
            'sub_categories' => ArticleCategoryResource::collection($this->subCategories),
            'media' => MediaResource::collection($this->media),
            // get cover where medialibrary media is_cover custom property is true
            'cover' => $this->getMedia(Article::MEDIA_COLLECTION_NAME)->where('is_cover', true)->first(),
            'tags' => $this->tags,
            'comments' => CommentResource::collection($this->comments),
            'interactions' => InteractionResource::collection($this->interactions),
            'location' => ($this->location) ? new LocationResource($this->location->first()) : null,
            // 'tagged_users' => UserResource::collection($this->taggedUsers),
            'count' => [
                'comments' => $this->comments_count ?? 0,
                'likes' => $this->interactions->where('type', Interaction::TYPE_LIKE)->count(),
                'dislikes' => $this->interactions->where('type', Interaction::TYPE_DISLIKE)->count(),
                'share' => $this->interactions->where('type', Interaction::TYPE_SHARE)->count(),
                'bookmarks' => $this->interactions->where('type', Interaction::TYPE_BOOKMARK)->count(),
                'views' => $this->views->count()
            ],
            'my_interactions' => [
                'like' => $this->interactions->where('type', Interaction::TYPE_LIKE)->where('user_id', auth()->user()->id)->first(),
                'dislike' => $this->interactions->where('type', Interaction::TYPE_DISLIKE)->where('user_id', auth()->user()->id)->first(),
                'share' => $this->interactions->where('type', Interaction::TYPE_SHARE)->where('user_id', auth()->user()->id)->first(),
                'bookmark' => $this->interactions->where('type', Interaction::TYPE_BOOKMARK)->where('user_id', auth()->user()->id)->first(),
            ],
            'user_liked' => (auth()->user()) ? $this->likes()->where('user_id', auth()->user()->id)->exists() : false,
            'user_bookmarked' => (auth()->user()) ? $this->interactions()->where('user_id', auth()->user()->id)->where('type', Interaction::TYPE_BOOKMARK)->exists() : false,
            'lang' => $this->lang,
            'is_imported' => $this->imports()->exists(),
            'source' => $this->source,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_diff' => $this->created_at->diffForHumans(),
            'updated_at_diff' => $this->updated_at->diffForHumans(),
        ];
    }
}
