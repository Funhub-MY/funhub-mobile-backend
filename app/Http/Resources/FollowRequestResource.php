<?php

namespace App\Http\Resources;

use App\Models\FollowRequest;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class FollowRequestResource extends JsonResource
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

        $tutorialSteps = Config::get('app.tutorial_steps', []);
        $completedSteps = $this->tutorialCompletions->pluck('tutorial_step')->toArray();

        $tutorialProgress = array_map(function ($step) use ($completedSteps) {
            return [
                'step' => $step,
                'completed' => in_array($step, $completedSteps)
            ];
        }, $tutorialSteps);

		$currentUser = $request->user();
		$isFollowing = false;
		$hasRequestedFollow = false;

		$isFollowing = $this->resource->followers->contains($currentUser->id);

		$hasRequestedFollow = FollowRequest::where('following_id', $currentUser->id)
					->where('user_id', $this->id)
					->where('accepted', false)
					->exists();

        return [
            'id' => $this->id,
            'name' => $name,
            'username' => $username,
            'email' => $this->email,
            'verified_email' => $this->hasVerifiedEmail(),
            'phone_country_code' => $this->phone_country_code,
            'phone_no' => $this->phone_no,
            'auth_provider' => $this->auth_provider,
            'avatar' => $avatar_url,
            'avatar_thumb' => $avatar_thumb_url,
            'bio' => $this->bio,
            'cover' => $this->cover_url,
            'articles_published_count' => $this->articles()->published()->count(),
            'following_count' => $this->followings()->where('status', User::STATUS_ACTIVE)->count(),
            'followers_count' => $this->followers()->where('status', User::STATUS_ACTIVE)->count(),
            'has_completed_profile' => $this->has_completed_profile,
            'has_article_personalization' => $this->has_article_personalization,
            'has_avatar' => $this->hasMedia('avatar'),
            'point_balance' => $this->point_balance,
            'unread_notifications_count' => $this->unreadNotifications()->count(),
			'is_following' => $isFollowing,
			'has_requested_follow' => $hasRequestedFollow,
//            'is_following' => ($request->user()) ? $this->resourcbeingFollowedRequestse->followers->contains($request->user()->id) : false,
//            'has_requested_follow' => ($request->user()) ? $this->resource->->contains('user_id', $request->user()->id) : false,
            'is_profile_private' => $this->profile_is_private,
            'dob' => $this->when($this->isAuthUser, $this->dob),
            'gender' => $this->when($this->isAuthUser, $this->gender),
            'job_title' => $this->when($this->isAuthUser, $this->job_title),
            'country_id' => $this->when($this->isAuthUser, $this->country_id),
            'state_id' => $this->when($this->isAuthUser, $this->state_id),
            'category_ids' => $this->when($this->isAuthUser, $this->articleCategoriesInterests->pluck('id')->toArray()),
            'onesignal_subscription_id' => $this->when($this->isAuthUser, $this->onesignal_subscription_id),
            'onesignal_user_id' => $this->when($this->isAuthUser, $this->onesignal_user_id),
            'created_at' => $this->created_at,
            'tutorial_progress' => $this->when($this->isAuthUser, $tutorialProgress),
        ];
    }
}
