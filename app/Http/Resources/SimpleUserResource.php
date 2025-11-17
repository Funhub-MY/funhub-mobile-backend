<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class SimpleUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        $name = null;
        $username = null;
        $avatar_url = null;
        $avatar_thumb_url = null;
        
        if ($this->status == User::STATUS_ARCHIVED) {
            $name = '用户已注销';
            $username = '用户已注销';
        } else {
            $name = $this->name;
            $username = $this->username;
            $avatar_url = $this->avatar_url;
            $avatar_thumb_url = $this->avatar_thumb_url;
        }

        $currentUser = $request->user();
        $isFollowing = false;
        $hasRequestedFollow = false;

        if ($currentUser) {
            // Check if the current user is already a follower
            $isFollowing = $this->resource->followers->contains($currentUser->id);

            // If already a follower, set has_requested_follow to false
            if ($isFollowing) {
                $hasRequestedFollow = false;
            } else {
                // Otherwise, check for follow requests
                $hasRequestedFollow = $this->resource->beingFollowedRequests->contains('user_id', $currentUser->id);
            }
        }

        return [
            'id' => $this->id,
            'name' => $name,
            'username' => $username,
            'avatar' => $avatar_url,
            'avatar_thumb' => $avatar_thumb_url,
            'has_avatar' => ($avatar_url || $avatar_thumb_url) ? true : false,
            'is_following' => $isFollowing,
            'has_requested_follow' => $hasRequestedFollow,
            'is_profile_private' => $this->profile_is_private,
            'created_at' => $this->created_at,
            'following_count' => $this->followings()->where('status', User::STATUS_ACTIVE)->count(),
            'followers_count' => $this->followers()->where('status', User::STATUS_ACTIVE)->count(),
            'articles_published_count' => $this->articles()->published()->count(),
        ];
    }
}
