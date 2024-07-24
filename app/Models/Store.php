<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    const STATUS_ACTIVE = 1;
    const STATUS_INACTIVE = 0;
    const STATUS_ARCHIVED = 2;

    const STATUS = [
        self::STATUS_ACTIVE => 'Listed',
        self::STATUS_INACTIVE => 'Unlisted',
        self::STATUS_ARCHIVED => 'Archived'
    ];

    const MEDIA_COLLECTION_PHOTOS = 'store_photos';

    /**
     * All of the relationships to be touched.
     *
     * @var array
     */
    protected $touches = ['merchant_offers'];

    protected $fillable = [
        'name',
        'status',
        'manager_name',
        'business_phone_no',
        'business_hours',
        'address',
        'address_postcode',
        'use_store_redeem',
        'redeem_code',
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
            // 'manager_name' => $this->manager_name,
            'onboarded' => ($this->merchant) ? true : false,
            'business_phone_no' => $this->business_phone_no,
            'business_hours' => $this->business_hours,
            'address' => $this->address,
            'address_postcode' => $this->address_postcode,
            'categories' => ($this->categories) ? $this->categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'name_translation' => $category->name_translation,
                ];
            }) : null,
            'category_ids' => $this->categories->pluck('id'),
            'parent_category_ids' => $this->parentCategories->pluck('id'),
            'child_category_ids' => $this->childCategories->pluck('id'),
            'ratings' => $this->storeRatings->avg('rating'),
            // 'lang' => $this->lang,
            // 'long' => $this->long,
            // 'is_hq' => $this->is_hq,
            'user_id' => $this->user_id,
            // 'state_id' => $this->state_id,
            // 'country_id' => $this->country_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'has_merchant_offers' => ($this->availableMerchantOffers->count() > 0) ? true : false,
            'merchant_offers' => ($this->availableMerchantOffers->count() > 0) ? $this->availableMerchantOffers->map(function ($merchantOffer) {
                return [
                    'id' => $merchantOffer->id,
                    'name' => $merchantOffer->name,
                    'brand_name' => $merchantOffer->user->merchant->brand_name ?? null,
                    'available_at' => $merchantOffer->available_at,
                    'available_until' => $merchantOffer->available_until,
                ];
            }) : null,
            'latest_articles_created_at' => ($this->articles) ? $this->articles->latest()->created_at : null,
            'articles' => ($this->articles) ? $this->articles->map(function ($article) {
                return [
                    'id' => $article->id,
                    'title' => $article->title,
                    'hastags' => $article->tags->pluck('name'),
                    'categories' => ($article->categories) ? $article->categories->map(function ($category) {
                        return [
                            'id' => $category->id,
                            'name' => $category->name,
                            'name_translation' => $category->name_translation,
                        ];
                    }) : null,
                ];
            }): null,
            '_geoloc' => ($this->lang && $this->long) ? [
                'lat' => (float) $this->lang,
                'lng' => (float) $this->long
            ] : null
        ];
    }

    public function shouldBeSearchable(): bool
    {
        if ($this->user_id) {
            // if has user_id, then make sure only approved merchant is searchable
            if ($this->merchant) {
                return $this->merchant->status === Merchant::STATUS_APPROVED && $this->status === self::STATUS_ACTIVE;
            }
        }

        // unonboarded merchants do not have user_id, make them searcheable
        // note: unonboarded merchants are auto synced from Article location -> stores
        return $this->status === self::STATUS_ACTIVE;;
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

    public function otherStores()
    {
        // self join same other stores but based on user id
        return $this->hasMany(Store::class, 'user_id', 'user_id')
            ->where('id', '!=', $this->id);
    }

    public function articles()
    {
        return $this->belongsToMany(Article::class, 'locatables', 'locatable_id', 'locatable_id')
            ->where('locatables.locatable_type', Store::class)
            ->whereIn('locatables.location_id', function ($query) {
                $query->select('location_id')
                    ->from('locatables as article_locatables')
                    ->where('article_locatables.locatable_type', Article::class);
            })
            ->withTimestamps();
    }

    public function merchant_offers()
    {
        return $this->belongsToMany(MerchantOffer::class, 'merchant_offer_stores')
            ->withTimestamps();
    }

    public function availableMerchantOffers()
    {
        return $this->belongsToMany(MerchantOffer::class, 'merchant_offer_stores')
            ->where('status', MerchantOffer::STATUS_PUBLISHED)
            ->where('available_at', '<=', now())
            ->where('available_until', '>=', now())
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

    public function childCategories()
    {
        return $this->belongsToMany(MerchantCategory::class, 'merchant_category_stores')
            ->whereNotNull('parent_id')
            ->withTimestamps();
    }

    public function parentCategories()
    {
        return $this->belongsToMany(MerchantCategory::class, 'merchant_category_stores')
            ->whereNull('parent_id')
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

    public function locationRatings()
    {
        return $this->hasManyThrough(
            LocationRating::class,
            Location::class,
            'id', // Foreign key on locations table
            'location_id', // Foreign key on location_ratings table
            'id', // Local key on stores table
            'id' // Local key on locations table
        )->join('locatables', function ($join) {
            $join->on('locations.id', '=', 'locatables.location_id')
                ->where('locatables.locatable_type', Store::class)
                ->whereColumn('locatables.locatable_id', 'stores.id');
        });
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

    public function scopeListed(Builder $query): void
    {
        $query->where($this->getTable() . '.status', self::STATUS_ACTIVE);
    }
}
