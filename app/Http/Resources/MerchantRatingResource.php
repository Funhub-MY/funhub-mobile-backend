<?php

namespace App\Http\Resources;

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
            $name = $this->name;
            $username = $this->username;
            $avatar_url = $this->avatar_url;
            $avatar_thumb_url = $this->avatar_thumb_url;
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
            'rating' => $this->rating,
            'comment' => $this->comment,
            'is_my_ratings' => $this->user_id == auth()->id(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
