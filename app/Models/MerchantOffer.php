<?php

namespace App\Models;

use App\Models\BaseModel;
use Carbon\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Builder;

class MerchantOffer extends BaseModel implements HasMedia, Auditable
{
    use HasFactory, InteractsWithMedia, Searchable, \OwenIt\Auditing\Auditable;

    const MEDIA_COLLECTION_NAME = 'merchant_offer_gallery';
    const MEDIA_COLLECTION_HORIZONTAL_BANNER = 'merchant_offer_horizontal_banner';

    protected $guarded = [
        'id'
    ];

    protected $appends = [
        // 'claimed_quantity',
         'name_sku'
    ];

	protected $casts = [
		'highlight_messages' => 'array',
	];

    const STATUS = [
        0 => 'Draft',
        1 => 'Published',
        2 => 'Archived'
    ];

    const STATUS_DRAFT = 0;
    const STATUS_PUBLISHED = 1;
    const STATUS_ARCHIVED = 2;

    // filterables
    const FILTERABLE = [
        'id',
        'name',
        'description',
        'available_at',
        'available_until',
        'sku'
    ];

    const CLAIM_SUCCESS = 1;
    const CLAIM_FAILED = 2;
    const CLAIM_AWAIT_PAYMENT = 3;
    const CLAIM_STATUS = [
        self::CLAIM_SUCCESS => 'Success',
        self::CLAIM_FAILED => 'Failed',
        self::CLAIM_AWAIT_PAYMENT => 'Awaiting Payment'
    ];

    /**
     * All of the relationships to be touched.
     *
     * @var array
     */
    // protected $touches = ['stores'];


    /**
     * Search Setup
     */
    public function searchableAs(): string
    {
        return config('scout.prefix').'merchant_offers_index';
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray()
    {
        $geolocs = null;
        $stores = null;
        $ratings = 0;
        $score = 0;
        $discountRate = 0;

        if ($this->stores()->count() > 0) {
            $geolocs = [];
            $stores = [];
            // load store location and ratings
            $this->stores->load(['location', 'storeRatings']);
            $totalRating = 0;
            $storeCount = 0;
            
            foreach ($this->stores as $store) {
                // calculate average rating for this store
                $storeRating = $store->storeRatings()->avg('rating') ?? 0;
                $totalRating += $storeRating;
                $storeCount++;
                
                // dont repeat if $stores already have same id
                if (!in_array($store->id, array_column($stores, 'id'))) {
                    $stores[] = [
                        'id' => $store->id,
                        'name' => $store->name,
                        'locations' => $store->location->map(function ($location) {
                            return [
                                'id' => $location->id,
                                'name' => $location->name,
                                'address' => $location->address,
                                'city' => $location->city,
                                'lat' => $location->lat,
                                'lng' => $location->lng,
                            ];
                        }),
                    ];
                }
                $firstStoreLocation = $store->location->first();
                if ($store->location && isset($firstStoreLocation->lat) && isset($firstStoreLocation->lng)) {
                    $geolocs[] = [
                        'lat' => floatval($firstStoreLocation->lat),
                        'lng' => floatval($firstStoreLocation->lng)
                    ];
                }
            }
            
            // calculate average rating across all stores
            $ratings = $storeCount > 0 ? round($totalRating / $storeCount, 1) : 0;
            
            // calculate discount rate
            if ($this->fiat_price > 0 && $this->discounted_point_fiat_price > 0) {
                $discountRate = round((($this->point_fiat_price - $this->discounted_point_fiat_price) / $this->point_fiat_price) * 100, 1);
            }
            
            // calculate final score (ratings + discount rate)
            $score = $ratings + $discountRate;
        }

        // load categories relationship
        $this->load('categories');

        // get state from store->location
        $states = [];
        foreach ($this->stores as $store) {
            $firstLocation = $store->location->first();
            if ($firstLocation && isset($firstLocation->state)) {
                $states[] = $firstLocation->state->name;
            }
        }

        if (in_array('Selangor', $states) 
        || in_array('Kuala Lumpur', $states)
        || in_array('Wilayah Persekutuan Kuala Lumpur', $states)) {
            $states[] = 'Klang Valley';
        }
        
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'merchant_id' => ($this->user && !empty($this->user->merchant)) ? $this->user->merchant->id : null,
            'stores' => $stores,
            'merchant' => [
                'id' => ($this->user && !empty($this->user->merchant)) ? $this->user->merchant->id : null,
                'business_name' => ($this->user && !empty($this->user->merchant)) ? $this->user->merchant->business_name : null,
                'user' => ($this->user) ? [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ] : null,
            ],
            'states' => $states,
            'status' => $this->status,
            'name' => $this->name,
            'unit_price' => $this->unit_price,
            'point_fiat_price' => (float) $this->point_fiat_price,
            'discounted_point_fiat_price' => (float) $this->discounted_point_fiat_price,
            'fiat_price' => (float) $this->fiat_price,
            'discounted_fiat_price' => (float) $this->discounted_fiat_price,
            'available_at_unix' => Carbon::parse($this->available_at)->unix(),
            'available_until_unix' => Carbon::parse($this->available_until)->unix(),
            // 'available_at' => $this->available_at,
            // 'available_until' => $this->available_until,
            'quantity' => $this->quantity,
            // 'claimed_quantity' => $this->claimed_quantity,
            'categories' => ($this->allOfferCategories?->map(function($category) {
                return ['id' => $category->id, 'name' => $category->name];
            })->values()->toArray()) ?? [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // 'created_at_diff' => $this->created_at->diffForHumans(),
            // 'updated_at_diff' => $this->updated_at->diffForHumans(),
            'category_ids' => $this->categories->pluck('id')->toArray(),
            '_geoloc' => $geolocs,
            'discount_rate' => $discountRate,
            'score' => $score,
            'ratings' => $ratings,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function campaign()
    {
        return $this->belongsTo(MerchantOfferCampaign::class, 'merchant_offer_campaign_id');
    }

    public function merchant()
    {
        // hasOneThrough user -> merchant
        return $this->hasOneThrough(Merchant::class, User::class, 'id', 'id', 'user_id', 'merchant_id');
    }

    public function offerCategories()
    {
        return $this->belongsToMany(MerchantOfferCategory::class, 'merchant_offer_merchant_offer_categories')
            ->where('parent_id', null)
            ->withTimestamps();
    }

    // NOTE since this is a self-referencing relationship, sync will override offerCategories!
    public function offerSubCategories()
    {
        return $this->belongsToMany(MerchantOfferCategory::class, 'merchant_offer_merchant_offer_categories')
            ->where('parent_id', '!=', null)
            ->withTimestamps();
    }

    public function allOfferCategories()
    {
        return $this->belongsToMany(MerchantOfferCategory::class, 'merchant_offer_merchant_offer_categories')
            ->withTimestamps();
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'merchant_offer_stores')
            ->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function interactions()
    {
        return $this->morphMany(Interaction::class, 'interactable');
    }

    public function likes()
    {
        return $this->morphMany(Interaction::class, 'interactable')
            ->where('type', Interaction::TYPE_LIKE);
    }

    // Claims are purchase of Merchant Offers
    public function claims()
    {
        return $this->belongsToMany(User::class, 'merchant_offer_user')
            ->withPivot('status', 'voucher_id', 'order_no', 'tax', 'discount', 'net_amount', 'remarks', 'purchase_method', 'quantity', 'transaction_no')
            ->withTimestamps();
    }

    // Used to track quanity of vouchers
    public function vouchers()
    {
        return $this->hasMany(MerchantOfferVoucher::class, 'merchant_offer_id', 'id');
    }

    public function unclaimedVouchers()
    {
        return $this->hasMany(MerchantOfferVoucher::class, 'merchant_offer_id', 'id')
            ->whereNull('owned_by_id');
    }

    // Redeems are claims that are redeemed(consumed) in store
    public function redeems()
    {
        return $this->belongsToMany(User::class, 'merchant_offer_claims_redemptions', 'merchant_offer_id', 'user_id')
            ->withPivot(['claim_id', 'quantity', 'transaction_id'])
            ->withTimestamps();
    }

    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    public function categories()
    {
        return $this->belongsToMany(MerchantCategory::class, 'merchant_category_merchant_offer')
            ->withTimestamps();
    }

    public function views()
    {
        return $this->morphMany(View::class, 'viewable');
    }

    public function articles()
    {
        return $this->belongsToMany(Article::class, 'articles_merchant_offers', 'merchant_offer_id', 'article_id')
            ->withTimestamps();
    }

    public function location()
    {
        return $this->morphToMany(Location::class, 'locatable');
    }

    /**
     * Scope a query to only include published offers.
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 1);
    }

    public function scopeAvailable(Builder $query): void
    {
        // available_at must be past
        $query->where('available_at', '<=', now());
    }

    public function scopeFlash(Builder $query): void
    {
        $query->where('flash_deal', true)
            ->where('available_until', '>=', now()) // must not be expired
            ->where('quantity', '>', 0); // must not be sold out
    }

    /**
     * DEPRECATED claimed_quantity, do query when needed
     * claimed_quantity
     */
    // public function getClaimedQuantityAttribute()
    // {
    //     return $this->claims()->wherePivot('status', self::CLAIM_SUCCESS)->count();
    // }

    public function getNameSkuAttribute()
    {
        return $this->name . ' (' . $this->sku . ')';
    }

    public function scopeSuccessClaimed(Builder $query)
    {
        $query->whereHas('claims', function ($query) {
            $query->wherePivot('status', self::CLAIM_SUCCESS);
        });
    }
}
