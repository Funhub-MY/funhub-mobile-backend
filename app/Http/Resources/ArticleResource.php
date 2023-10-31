<?php

namespace App\Http\Resources;

use App\Models\Article;
use App\Models\Interaction;
use App\Models\Location;
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
                'username' => $this->user->username,
                'avatar' => $this->user->avatar_url,
                'avatar_thumb' => $this->user->avatar_thumb_url,
                'following_count' => $this->user_followings_count,
                'followers_count' => $this->user_followers_count,
            ],
            'categories' => ArticleCategoryResource::collection($this->categories),
            'sub_categories' => ArticleCategoryResource::collection($this->subCategories),
            'media' => MediaResource::collection($this->media),
            // get cover where medialibrary media is_cover custom property is true
            'cover' => $this->getMedia(Article::MEDIA_COLLECTION_NAME)->where('is_cover', true)->first(),
            'tags' => $this->tags,
            // 'comments' => CommentResource::collection($this->comments),
            'interactions' => InteractionResource::collection($this->interactions),
            'location' => $location,
            // 'tagged_users' => UserResource::collection($this->taggedUsers),
            'count' => [
                'comments' => $this->comments_count ?? 0,
                'likes' => $this->interactions->where('type', Interaction::TYPE_LIKE)->count(),
                'dislikes' => $this->interactions->where('type', Interaction::TYPE_DISLIKE)->count(),
                'share' => $this->interactions->where('type', Interaction::TYPE_SHARE)->count(),
                'bookmarks' => $this->interactions->where('type', Interaction::TYPE_BOOKMARK)->count(),
                'views' => $this->views_count ?? 0,
            ],
            'my_interactions' => [
                'like' => $this->interactions->where('type', Interaction::TYPE_LIKE)->where('user_id', auth()->user()->id)->first(),
                'dislike' => $this->interactions->where('type', Interaction::TYPE_DISLIKE)->where('user_id', auth()->user()->id)->first(),
                'share' => $this->interactions->where('type', Interaction::TYPE_SHARE)->where('user_id', auth()->user()->id)->first(),
                'bookmark' => $this->interactions->where('type', Interaction::TYPE_BOOKMARK)->where('user_id', auth()->user()->id)->first(),
            ],
            'user_liked' => (auth()->user()) ? $this->interactions->where('type', Interaction::TYPE_LIKE)->where('user_id', auth()->user()->id)->count() > 0 : false,
            'user_bookmarked' => (auth()->user()) ? $this->interactions->where('type', Interaction::TYPE_BOOKMARK)->where('user_id', auth()->user()->id)->count() > 0 : false,
            'lang' => $this->lang,
            'is_imported' => $this->imports->count() > 0,
            'source' => $this->source,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_diff' => $this->created_at->diffForHumans(),
            'updated_at_diff' => $this->updated_at->diffForHumans(),
        ];
    }
}
