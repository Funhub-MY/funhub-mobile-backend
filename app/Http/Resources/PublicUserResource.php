<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicUserResource extends JsonResource
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
            'avatar' => $avatar_url,
            'avatar_thumb' => $avatar_thumb_url,
            'bio' => $this->bio,
            'cover' => $this->cover_url,
            'articles_published_count' => $this->articles()->published()->count(),
            'following_count' => $this->followings()->count(),
            'followers_count' => $this->followers()->count(),
            'has_avatar' => $this->hasMedia('avatar'),
            'is_profile_private' => $this->profile_is_private,
        ];
    }
}
