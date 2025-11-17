<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use App\Models\FollowRequest;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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

        $tutorialSteps = Config::get('app.tutorial_steps', []);
        
        // check override setting from cache, if not exists, get from DB and cache it
        $overrideAllTutorials = Cache::remember('setting_override_all_tutorial_completed', 3600, function () {
            return Setting::where('key', 'override_all_tutorial_completed')->value('value') === 'true';
        });

        $completedSteps = $this->tutorialCompletions->pluck('tutorial_step')->toArray();

        $tutorialProgress = array_map(function ($step) use ($completedSteps, $overrideAllTutorials) {
            return [
                'step' => $step,
                'completed' => $overrideAllTutorials ? true : in_array($step, $completedSteps)
            ];
        }, $tutorialSteps);

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
//            'is_following' => ($request->user()) ? $this->resource->followers->contains($request->user()->id) : false,
//            'has_requested_follow' => ($request->user()) ? $this->resource->beingFollowedRequests->contains('user_id', $request->user()->id) : false,
            'is_profile_private' => $this->profile_is_private,
            'account_restricted' => ($this->account_restricted == 1) ? true : false,
            'account_restricted_until' => $this->account_restricted_until,
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
            'account_status' => $this->status,
            'rsvp' => $this->rsvp,
        ];
    }
}
