<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Jobs\SyncUserWithOneSignal;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Mail\EmailVerification;
use Illuminate\Support\Facades\Mail;
use App\Services\OneSignalService;

class User extends Authenticatable implements HasMedia, FilamentUser, Auditable
{
    use SoftDeletes, HasApiTokens, HasFactory, Notifiable, HasRoles, InteractsWithMedia, Searchable, \OwenIt\Auditing\Auditable;

    const USER_VIDEO_UPLOADS = 'user_video_uploads';
    const USER_AVATAR = 'user_avatar';
    const USER_UPLOADS = 'user_uploads';

    const STATUS_ACTIVE = 1;
    const STATUS_SUSPENDED = 2;
    const STATUS_ARCHIVED = 3;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [
        'id'
    ];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'full_phone_no',
        'avatar_url',
        'avatar_thumb_url',
        'point_balance',
        'auth_provider',
        'has_completed_profile',
        'cover_url',
        'profile_is_private'
    ];

    protected static function boot()
    {
        parent::boot();

        // static::created(function ($user) {
        //     dispatch(new SyncUserWithOneSignal($user));
        // });

        // static::updated(function ($user) {
        //     dispatch(new SyncUserWithOneSignal($user));
        // });
    }

    public function canAccessFilament(): bool
    {
        return $this->hasRole('staff') || $this->hasRole('admin') || $this->hasRole('super_admin') || $this->hasRole('merchant');
    }

    public function sendEmailVerificationNotification()
    {
        $user = $this;

        // generate 6 digit token
        $token = rand(100000, 999999);
        $user->update([
            'email_verification_token' => $token,
        ]);

        try {
            // fire email verification notification
            Mail::to($user->email)->send(new EmailVerification($user->name, $token));

            Log::info('[Email Verification] Send email verification notification', [
                'user_id' => $user->id,
                'email' => $user->email,
                'token' => $token,
            ]);
        } catch (\Exception $ex) {
            Log::error('[Error] Send email verification notification ', [
                'user' => $user,
                'error' => $ex->getMessage()
            ]);
        }
    }

    /**
     * Specifies the user's FCM token
     *
     * @return string|array
     * @return string|array
     */
    public function routeNotificationForFcm()
    {
        return $this->fcm_token;
    }

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];


    /**
     * Search Setup
     */
    public function searchableAs(): string
    {
        return config('scout.prefix').'users_index';
    }

    public function toSearchableArray()
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'thumb' => $this->avatar_thumb_url,
        ];
    }

    public function shouldBeSearchable() : bool
    {
        return $this->status == self::STATUS_ACTIVE && $this->name != null;
    }

    /**
     * Spatia Media conversions for thumbnail
     */
    public function registerAllMediaConversions() : void {
        $this->addMediaConversion('thumb')
                ->performOnCollections('avatar')
                ->width(60)
                ->height(60);
    }

    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function commentLikes()
    {
        return $this->hasMany(CommentLike::class);
    }

    public function interactions()
    {
        return $this->hasMany(Interaction::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    /**
     * User created article categories
     */
    public function articleCategories()
    {
        return $this->hasMany(ArticleCategory::class);
    }

    /**
     * User interest article categories (settings)
     */
    public function articleCategoriesInterests()
    {
        return $this->belongsToMany(ArticleCategory::class, 'user_article_categories')
            ->withTimestamps();
    }

    public function articleTags()
    {
        return $this->hasMany(ArticleTag::class);
    }

    public function merchant()
    {
        return $this->hasOne(Merchant::class);
    }

    // Important Note:: This is to indicate what merchant_offers has created by the users.
    public function merchant_offers()
    {
        return $this->hasMany(MerchantOffer::class);
    }

    // Important Note:: This is to indicate what merchant_offers has been claimed by the users.
    public function claimed_merchant_offers()
    {
        return $this->belongsToMany(MerchantOffer::class, 'merchant_offer_user')
            ->withPivot('status', 'voucher_id', 'order_no', 'amount', 'tax', 'discount', 'net_amount', 'remarks')
            ->withTimestamps();
    }

    public function stores()
    {
         return $this->hasMany(Store::class);
    }

    public function interestArticleTags()
    {
        return $this->belongsToMany(ArticleTag::class);
    }

    public function interestArticleCategories()
    {
        return $this->belongsToMany(ArticleCategory::class);
    }

    public function settings()
    {
        return $this->hasMany(UserSetting::class);
    }

    public function followings()
    {
        return $this->belongsToMany(User::class, 'users_followings', 'user_id', 'following_id')
            ->withTimestamps();
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'users_followings', 'following_id', 'user_id');
    }

    public function hiddenFromArticles()
    {
        return $this->belongsToMany(Article::class, 'articles_hidden_users')
            ->withPivot('hide_until')
            ->withTimestamps();
    }

    public function rss_channel()
    {
        return $this->hasOne(RssChannel::class);
    }

    public function pointLedgers()
    {
        return $this->hasMany(PointLedger::class);
    }

    public function pointComponentsLedger()
    {
        return $this->hasMany(PointComponentLedger::class);
    }
    // missions user created
    public function missions()
    {
        return $this->hasMany(Mission::class);
    }

    // missions users are participating
    public function missionsParticipating()
    {
        return $this->belongsToMany(Mission::class, 'missions_users')
            ->withPivot('id', 'is_completed', 'last_rewarded_at', 'started_at', 'current_values', 'completed_at')
            ->withTimestamps();
    }

    public function reports()
    {
        return $this->morphMany(Reports::class, 'reportable');
    }

    public function articlesTaggedIn()
    {
        return $this->belongsToMany(Article::class, 'articles_tagged_users', 'user_id', 'article_id')
            ->withTimestamps();
    }

    // users that this user has blocked
    public function usersBlocked()
    {
        return $this->hasMany(UserBlock::class, 'user_id')
            ->where('blockable_type', User::class);
    }

    public function blockedBy()
    {
        return $this->hasMany(UserBlock::class, 'blockable_id')
            ->where('blockable_type', User::class);
    }

    public function articleRanks()
    {
        return $this->hasMany(UserArticleRank::class, 'user_id');
    }

    public function userAccountDeletion()
    {
        return $this->hasOne(UserAccountDeletion::class, 'user_id');
    }

    public function supportRequests()
    {
        return $this->hasMany(SupportRequest::class, 'requestor_id');
    }

    public function followRequests()
    {
        return $this->hasMany(FollowRequest::class, 'user_id');
    }

    public function beingFollowedRequests()
    {
        return $this->hasMany(FollowRequest::class, 'following_id');
    }

    public function campaignAnswers()
    {
        return $this->belongsToMany(CampaignQuestion::class, 'campaigns_questions_answers_users', 'user_id', 'campaign_question_id')
          ->withPivot('answer')
          ->withTimestamps();
    }

    public function profilePrivacySettings()
    {
        return $this->hasMany(UserProfilePrivateSetting::class, 'user_id');
    }

    public function commentsTagged()
    {
        $this->belongsToMany(Comment::class, 'comments_users', 'user_id', 'comment_id')
            ->withTimestamps();
    }

    public function articleFeedWhitelist()
    {
        return $this->hasOne(ArticleFeedWhitelistUser::class, 'user_id');
    }

    public function referredBy()
    {
        return $this->belongsTo(User::class, 'referred_by_id');
    }

    public function merchantRatings()
    {
        return $this->hasMany(MerchantRating::class, 'user_id');
    }

    public function storeRatings()
    {
        return $this->hasMany(StoreRating::class, 'user_id');
    }

    public function articleEngagements()
    {
        return $this->belongsToMany(ArticleEngagement::class, 'article_engagements_users', 'user_id', 'article_id')
            ->withTimestamps();
    }

    /**
     * Get the user's point balance
     */
    public function getPointBalanceAttribute()
    {
        return $this->pointLedgers()->orderBy('id', 'desc')->first()->balance ?? 0;
    }

    /**
     * Get the user's full phone number
     *
     * @return string
     */
    public function getFullPhoneNoAttribute()
    {
        // response 60123456789
        return $this->phone_country_code . $this->phone_no;
    }

    /**
     * Get user avatar
     *
     * @return string
     */
    public function getAvatarUrlAttribute()
    {
        $avatar = $this->getMedia('avatar')->first();
        if ($avatar) {
            return $avatar->getUrl();
        } else {
            return null;
        }
    }

    /**
     * Get user cover
     *
     * @return string
     */
    public function getCoverUrlAttribute()
    {
        $cover = $this->getMedia('cover')->first();
        if ($cover) {
            return $cover->getUrl();
        } else {
            return null;
        }
    }

    /**
     * Get user avatar thumbnail
     *
     * @return string
     */
    public function getAvatarThumbUrlAttribute()
    {
        $avatar = $this->getMedia('avatar')->first();
        if ($avatar) {
            return $avatar->getUrl('thumb');
        } else {
            return 'https://ui-avatars.com/api/?name=' . $this->name;
        }
    }

    /**
     * Set username attribute
     *
     * When user save a name, it also overrides the username if its null
     *
     * @param string $value
     * @return void
     */
    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value;

        if (!$this->username) {
            try {
                // check if english language
                if (preg_match('/^[a-zA-Z]+$/', $value)) {
                    // only allow 9 character length max for username
                    // check if username exists, if yes pad number
                    $username = strtolower(substr($value, 0, 9));
                    $user = User::where('username', $username)->first();
                    if ($user) {
                        $username = $username . rand(1, 9);
                    }
                    // remove any empty space
                    $username = str_replace(' ', '', $username);
                    $this->attributes['username'] = $username;
                } else {
                    // random 6 character username with 3 numbers
                    $this->attributes['username'] = strtolower( Str::random(6) . rand(100, 999));
                }
            } catch (\Throwable $th) {
                Log::error('[Error] Username invalid when set name attribute ', [
                    'name' => $value,
                    'username' => $this->username,
                    'error' => $th->getMessage()
                ]);

                // use random 6 character username with 3 numbers
                 $this->attributes['username'] = strtolower( Str::random(6) . rand(100, 999));
                //throw $th;
            }
        }
    }

    /**
     * Get user's auth provider
     */
    public function getAuthProviderAttribute()
    {
        if ($this->google_id) {
            return 'google';
        } elseif ($this->facebook_id) {
            return 'facebook';
        } elseif($this->apple_id) {
            return 'apple';
        } else {
            return 'phone_no';
        }
    }

    /**
     * Get user has completed profile
     */
    public function getHasCompletedProfileAttribute()
    {
        // ensure name and email are set for social auth user
        // else ensure name, email, password are set for phone no sms otp login user
        if ($this->auth_provider == 'phone_no') {
            return !is_null($this->name);
        } else if ($this->auth_provider == 'facebook') {
            return !is_null($this->name) && !empty($this->name);
        } else {
            return $this->name && $this->email;
        }
    }

    public function getProfileIsPrivateAttribute()
    {
        $privacy = $this->profilePrivacySettings()
            ->orderBy('id', 'desc')
            ->first();

         return $privacy ? !($privacy->profile == 'public') : false;
    }

    /**
     * Phone No attribute mutator
     */
    protected function phoneNo(): Attribute
    {
        return Attribute::make(
            // set if phone_no start with zero remove it
            set: fn (?string $value) => $value ? ltrim($value, '0') : null,
        );
    }

    /**
     * Get Point Component balance
     *
     * @param RewardComponent $component
     */
    public function getPointComponentBalance($component)
    {
        return $this->pointComponentsLedger()->where('pointable_type', RewardComponent::class)
            ->where('pointable_id', $component->id)
            ->orderBy('id', 'desc')->first()->balance ?? 0;
    }

    /**
     * Get Is User Blocking User
     *
     * @param User $user
     * @return boolean
     */
    public function isBlocking($user)
    {
        return $this->usersBlocked()->where('blockable_id', $user->id)->exists();
    }

    /**
     * Unfollow a user
     *
     * @param User $user
     * @return void
     */
    public function unfollow($user)
    {
        return $this->followings()->detach($user->id);
    }

    public function views()
    {
        return $this->morphMany(View::class, 'viewable');
    }
}
