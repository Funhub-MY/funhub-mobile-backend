<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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

class User extends Authenticatable implements HasMedia, FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, InteractsWithMedia, Searchable;

    const USER_VIDEO_UPLOADS = 'user_video_uploads';
    const USER_AVATAR = 'user_avatar';
    const USER_UPLOADS = 'user_uploads';

    const STATUS_ACTIVE = 1;
    const STATUS_SUSPENDED = 2;

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
    ];

    public function canAccessFilament(): bool
    {
        return $this->hasRole('staff');
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
        return 'users_index';
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
        return $this->status == self::STATUS_ACTIVE;
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
            ->withPivot('status', 'order_no', 'amount', 'tax', 'discount', 'net_amount', 'remarks')
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
        return $this->belongsToMany(User::class, 'users_followings', 'user_id', 'following_id');
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
            ->withPivot('is_completed', 'started_at', 'current_value')
            ->withTimestamps();
    }
    
    public function reports()
    {
        return $this->morphMany(Reports::class, 'reportable');
    }

    // users that this user has blocked
    public function usersBlocked()
    {
        return $this->morphMany(UserBlock::class, 'blockable')
            ->where('blockable_type', User::class);
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
            return 'https://ui-avatars.com/api/?name=' . $this->name;
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
            return $this->name && $this->email && $this->password;
        } else {
            return $this->name && $this->email;
        }
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
}
