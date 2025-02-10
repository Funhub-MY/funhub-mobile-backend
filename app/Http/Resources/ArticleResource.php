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
        $currentUser = $request->user();
        $currentUserId = $currentUser ? $currentUser->id : null;
        $interactions = $this->whenLoaded('interactions');
        
        // optimize location query
        $location = null;
        if ($this->has('location')) {
            $loc = $this->location->first();
            if ($loc && $loc->has('ratings')) {
                $articleOwnerRating = $loc->ratings->where('user_id', $this->user_id)->first();
                $location = [
                    'id' => $loc->id,
                    'name' => $loc->name,
                    'address' => $loc->full_address,
                    'article_owner_rating' => $articleOwnerRating ? $articleOwnerRating->rating : null,
                    'lat' => floatval($loc->lat),
                    'lng' => floatval($loc->lng),
                ];
            }
        }

        // get user data from eager loaded relationship
        $user = $this->whenLoaded('user');
        $userAvatar = null;
        if ($user && !empty($user->avatar_thumb_url)) {
            $userAvatar = $user->avatar_thumb_url;
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'user' => $this->when($user, function() use ($user, $userAvatar, $request, $currentUserId) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'avatar' => $userAvatar,
                    'avatar_thumb' => $userAvatar, // Use the same URL for thumb to reduce queries
                    'following_count' => $this->user_followings_count,
                    'followers_count' => $this->user_followers_count,
                    'has_avatar' => $userAvatar !== null,
                    'is_following' => $currentUserId ? $user->followers->contains($currentUserId) : false,
                    'has_requested_follow' => $currentUserId ? $user->beingFollowedRequests->contains('user_id', $currentUserId) : false,
                ];
            }),
            'categories' => ArticleCategoryResource::collection($this->categories->load('media')),
            'sub_categories' => ArticleCategoryResource::collection($this->subCategories->load('media')),
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
                'likes' => $interactions ? $interactions->where('type', Interaction::TYPE_LIKE)->count() : 0,
                'dislikes' => $interactions ? $interactions->where('type', Interaction::TYPE_DISLIKE)->count() : 0,
                'share' => $interactions ? $interactions->where('type', Interaction::TYPE_SHARE)->count() : 0,
                'bookmarks' => $interactions ? $interactions->where('type', Interaction::TYPE_BOOKMARK)->count() : 0,
                'views' => $this->views_count ?? 0,
            ],
            'my_interactions' => $this->when($currentUserId, function () use ($interactions, $currentUserId) {
                return [
                    'like' => $interactions ? $interactions->where('type', Interaction::TYPE_LIKE)->where('user_id', $currentUserId)->first() : null,
                    'dislike' => $interactions ? $interactions->where('type', Interaction::TYPE_DISLIKE)->where('user_id', $currentUserId)->first() : null,
                    'share' => $interactions ? $interactions->where('type', Interaction::TYPE_SHARE)->where('user_id', $currentUserId)->first() : null,
                    'bookmark' => $interactions ? $interactions->where('type', Interaction::TYPE_BOOKMARK)->where('user_id', $currentUserId)->first() : null,
                ];
            }),
            'has_merchant_offer' => (isset($this->has_merchant_offer) && $this->has_merchant_offer) ? true : false,
            'user_liked' => $currentUserId && $interactions ? $interactions->where('type', Interaction::TYPE_LIKE)->where('user_id', $currentUserId)->isNotEmpty() : false,
            'user_bookmarked' => $currentUserId && $interactions ? $interactions->where('type', Interaction::TYPE_BOOKMARK)->where('user_id', $currentUserId)->isNotEmpty() : false,
            'lang' => $this->lang,
            'visibility' => $this->visibility,
            'is_imported' => $this->imports->count() > 0,
            'source' => $this->source,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_diff' => $this->created_at->diffForHumans(),
            'updated_at_diff' => $this->updated_at->diffForHumans(),
        ];
    }
}
