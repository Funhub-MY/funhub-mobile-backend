<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'avatar',
        'email',
        'password',
        'email_verified_at'
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

    public function articleCategories()
    {
        return $this->hasMany(ArticleCategory::class);
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

}
