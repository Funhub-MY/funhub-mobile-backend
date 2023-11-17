<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    protected $isAuthUser;

    public function __construct($resource, $isAuthUser = false)
    {
        parent::__construct($resource);
        $this->isAuthUser = $isAuthUser;
    }

    /**
     * The "data" wrapper that should be applied.
     *
     * @var string|null
     */
    // public static $wrap = 'user';

    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
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
            'name' => $name,
            'username' => $username,
            'email' => $this->email,
            'verified_email' => $this->hasVerifiedEmail(),
            'auth_provider' => $this->auth_provider,
            'avatar' => $avatar_url,
            'avatar_thumb' => $avatar_thumb_url,
            'bio' => $this->bio,
            'cover' => $this->cover_url,
            'articles_published_count' => $this->articles()->published()->count(),
            'following_count' => $this->followings()->count(),
            'followers_count' => $this->followers()->count(),
            'has_completed_profile' => $this->has_completed_profile,
            'has_avatar' => $this->hasMedia('avatar'),
            'point_balance' => $this->point_balance,
            'unread_notifications_count' => $this->unreadNotifications()->count(),
            'is_following' => ($request->user()) ? $this->resource->followers->contains($request->user()->id) : false,
            'dob' => $this->when($this->isAuthUser, $this->dob),
            'gender' => $this->when($this->isAuthUser, $this->gender),
            'job_title' => $this->when($this->isAuthUser, $this->job_title),
            'country_id' => $this->when($this->isAuthUser, $this->country_id),
            'state_id' => $this->when($this->isAuthUser, $this->state_id),
            'category_ids' => $this->when($this->isAuthUser, $this->articleCategoriesInterests->pluck('id')->toArray()),
        ];
    }
}
