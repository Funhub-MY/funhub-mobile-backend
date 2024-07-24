<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Article;
use App\Models\Interaction;
use App\Models\Comment;

class PublicArticleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $location = null;
        if ($this->has('location')) {
            $loc = $this->location->first();

            // if artilce locaiton has ratings, get current article owner's ratings
            if ($loc && $loc->has('ratings')) {
                $articleOwnerRating = $loc->ratings->where('user_id', $this->user->id)->first();
                $location = [
                    'id' => $loc->id,
                    'name' => $loc->name,
                    'address' => $loc->full_address,
                    'article_owner_rating' => ($articleOwnerRating) ? $articleOwnerRating->rating : null,
                    'lat' =>  floatval($loc->lat),
                    'lng' => floatval($loc->lng),
                ];
            }
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => $this->user->avatar_url,
                'avatar_thumb' => $this->user->avatar_thumb_url,
                'followers_count' => $this->user->followers()->count(),
            ],
            'media' => MediaResource::collection($this->media),
            'is_video' => $this->type == 'video',
            'cover' => $this->getMedia(Article::MEDIA_COLLECTION_NAME)->where('is_cover', true)->first(),
            // 'tags' => $this->tags,
            // 'interactions' => InteractionResource::collection($this->interactions),
            'location' => $location,
            'count' => [
                'comments' => $this->comments_count ?? 0,
                'likes' => $this->interactions->where('type', Interaction::TYPE_LIKE)->count(),
                'dislikes' => $this->interactions->where('type', Interaction::TYPE_DISLIKE)->count(),
                'share' => $this->interactions->where('type', Interaction::TYPE_SHARE)->count(),
                'bookmarks' => $this->interactions->where('type', Interaction::TYPE_BOOKMARK)->count(),
                'views' => $this->views_count ?? 0,
            ],
            'lang' => $this->lang,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_diff' => $this->created_at->diffForHumans(),
            'updated_at_diff' => $this->updated_at->diffForHumans(),
        ];
    }
}
