<?php

namespace App\Models;

use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Store extends BaseModel implements HasMedia, Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable, Searchable, InteractsWithMedia;

    const MEDIA_COLLECTION_PHOTOS = 'store_photos';

    protected $fillable = [
        'name',
        'manager_name',
        'business_phone_no',
        'business_hours',
        'address',
        'address_postcode',
        'lang',
        'long',
        'is_hq',
        'user_id',
        'merchant_id',
        'state_id',
        'country_id',
        'deleted_at',
        'created_at',
        'updated_at'
    ];


    public static function getLatLngAttributes(): array
    {
        return [
            'lat' => 'lang',
            'lng' => 'long',
        ];
    }

    /**
     * Search Setup
     */
    public function searchableAs(): string
    {
        return config('scout.prefix').'stores_index';
    }

    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'manager_name' => $this->manager_name,
            'business_phone_no' => $this->business_phone_no,
            'business_hours' => $this->business_hours,
            'address' => $this->address,
            'address_postcode' => $this->address_postcode,
            'lang' => $this->lang,
            'long' => $this->long,
            'is_hq' => $this->is_hq,
            'user_id' => $this->user_id,
            'state_id' => $this->state_id,
            'country_id' => $this->country_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'has_merchant_offers' => ($this->merchant_offers->count() > 0) ? true : false,
            '_geoloc' => ($this->lang && $this->long) ? [
                'lat' => (float) $this->lang,
                'lng' => (float) $this->long
            ] : null
        ];
    }
    public function shouldBeSearchable(): bool
    {
        // only approved merchant their stores can be searcheable
        if ($this->merchant) {
            return $this->merchant->status === Merchant::STATUS_APPROVED;
        }
        return false;
    }

    public function merchant()
    {
        return $this->hasOneThrough(
            Merchant::class,  // Final model
            User::class,      // Intermediate model
            'id',             // Foreign key on the intermediate model (users.id)
            'user_id',        // Foreign key on the final model (merchants.user_id)
            'user_id',        // Local key on the current model (stores.user_id)
            'id'              // Local key on the intermediate model (users.id)
        );
    }

    // a store is related to an article through a shared location
    public function articles()
    {
        return $this->belongsToMany(Article::class, 'locatables', 'locatable_id', 'locatable_id')
            ->where('locatables.locatable_type', Store::class)
            ->wherePivotIn('location_id', function ($query) {
                $query->select('location_id')
                    ->from('locatables')
                    ->where('locatable_type', Article::class);
            })
            ->withTimestamps();
    }

    public function merchant_offers()
    {
        return $this->belongsToMany(MerchantOffer::class, 'merchant_offer_stores')
            ->withTimestamps();
    }

    public function merchant_offer_campaigns()
    {
        return $this->belongsToMany(MerchantOfferCampaign::class, 'merchant_offer_campaign_stores')
            ->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function categories()
    {
        return $this->belongsToMany(MerchantCategory::class, 'merchant_category_stores')
                ->withTimestamps();
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function location()
    {
        return $this->morphToMany(Location::class, 'locatable');
    }


    public function storeRatings()
    {
        return $this->hasMany(StoreRating::class);
    }

    public function interactions()
    {
        return $this->morphMany(Interaction::class, 'interactable');
    }

    // a store has many ratingCategories through storeRatings
    public function ratingCategories()
    {
        return $this->hasManyThrough(
            RatingCategory::class,  // Final model
            StoreRating::class,     // Intermediate model
            'store_id',             // Foreign key on the intermediate model (store_ratings.store_id)
            'id',                   // Foreign key on the final model (rating_categories.id)
            'id',                   // Local key on the current model (stores.id)
            'rating_category_id'    // Local key on the intermediate model (store_ratings.rating_category_id)
        );
    }

}
