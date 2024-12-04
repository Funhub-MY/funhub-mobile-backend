<?php

namespace App\Http\Resources;

use App\Models\Interaction;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantRatingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        if ($this->user->status == User::STATUS_ARCHIVED) {
            $name = '用户已注销';
            $username = '用户已注销';
            $avatar_url = null;
            $avatar_thumb_url = null;
        } else {
            $name = $this->user->name;
            $username = $this->user->username;
            $avatar_url = $this->user->avatar_url;
            $avatar_thumb_url = $this->user->avatar_thumb_url;
        }

        return [
            'id' => $this->id,
            'merchant_id' => $this->merchant_id,
            'user_id' => $this->user_id,
            'user' => [
                'id' => $this->user->id,
                'name' => $name,
                'username' => $username,
                'avatar' => $avatar_url,
                'avatar_thumb' => $avatar_thumb_url,
            ],
            'rating' => floatval($this->rating),
            'comment' => $this->comment,
            'likes_count' => $this->likes_count ?? 0,
            'dislikes_count' => $this->dislikes_count ?? 0,
            'user_liked' => (auth()->user()) ? $this->interactions->where('type', Interaction::TYPE_LIKE)->where('user_id', auth()->user()->id)->count() > 0 : false,
            'user_disliked' => (auth()->user()) ? $this->interactions->where('type', Interaction::TYPE_DISLIKE)->where('user_id', auth()->user()->id)->count() > 0 : false,
            'is_my_ratings' => $this->user_id == auth()->id(),
            'total_ratings_for_merchant' => $this->merchant->merchantRatings()->count(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
