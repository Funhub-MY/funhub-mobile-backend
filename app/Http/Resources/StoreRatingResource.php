<?php

namespace App\Http\Resources;

use App\Models\Interaction;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreRatingResource extends JsonResource
{
    public function toArray($request)
    {
        $user = $this->user;

        if ($user->status == User::STATUS_ARCHIVED) {
            $name = '用户已注销';
            $username = '用户已注销';
            $avatar_url = null;
            $avatar_thumb_url = null;
        } else {
            $name = $user->name;
            $username = $user->username;
            $avatar_url = $user->avatar_url;
            $avatar_thumb_url = $user->avatar_thumb_url;
        }

        $authenticatedUser = auth()->user();

        $myLikeInteraction = $authenticatedUser ? $this->interactions()->where('type', Interaction::TYPE_LIKE)->where('user_id', $authenticatedUser->id)->first() : null;
        $myDislikeInteraction = $authenticatedUser ? $this->interactions()->where('type', Interaction::TYPE_DISLIKE)->where('user_id', $authenticatedUser->id)->first() : null;

        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'user_id' => $this->user_id,
            'user' => [
                'id' => $user->id,
                'name' => $name,
                'username' => $username,
                'avatar' => $avatar_url,
                'avatar_thumb' => $avatar_thumb_url,
            ],
            'rating' => number_format(floatval($this->rating), 1),
            'categories' => RatingCategoryResource::collection($this->whenLoaded('ratingCategories')),
            'comment' => $this->comment,
            'likes_count' => $this->likes_count ?? 0,
            'dislikes_count' => $this->dislikes_count ?? 0,
            'user_liked' => (bool) $myLikeInteraction,
            'user_disliked' => (bool) $myDislikeInteraction,
            'is_my_ratings' => $this->user_id == ($authenticatedUser ? $authenticatedUser->id : null),
            'my_like_interaction_id' => $myLikeInteraction->id ?? null,
            'my_dislike_interaction_id' => $myDislikeInteraction->id ?? null,
            'total_ratings_for_store' => $this->user->store_ratings_count,
            'article_id' => $this->article_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
