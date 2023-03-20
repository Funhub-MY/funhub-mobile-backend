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

class User extends Authenticatable implements HasMedia
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, InteractsWithMedia;

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
        'full_phone_no'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function interactions()
    {
        return $this->hasMany(Interaction::class);
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
        return $this->belongsToMany(MerchantOffer::class, 'merchant_offer_user')->withPivot('status');
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

    public function hiddenFromArticles()
    {
        return $this->belongsToMany(Article::class, 'articles_hidden_users')
            ->withPivot('hide_until')
            ->withTimestamps();
    }

    /**
     * Get the user's full phone number
     */
    public function getFullPhoneNoAttribute()
    {
        // response 60123456789
        return $this->phone_country_code . $this->phone_no;
    }
}
