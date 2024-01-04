<?php

namespace App\Models;

use App\Models\BaseModel;
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
        'claimed_quantity', 'name_sku'
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
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'merchant_id' => ($this->user) ? $this->user->merchant->id : null,
            'store' => [
                'id' => ($this->store) ? $this->store->id : null,
                'name' => ($this->store) ? $this->store->name : null,
            ],
            'merchant' => [
                'id' => ($this->user) ? $this->user->merchant->id : null,
                'business_name' => ($this->user) ? $this->user->merchant->business_name : null,
                'user' => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ],
            ],
            'status' => $this->status,
            'name' => $this->name,
            'description' => $this->description,
            'unit_price' => $this->unit_price,
            'available_at' => $this->available_at,
            'available_until' => $this->available_until,
            'quantity' => $this->quantity,
            'claimed_quantity' => $this->claimed_quantity,
            'categories' => $this->categories,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_diff' => $this->created_at->diffForHumans(),
            'updated_at_diff' => $this->updated_at->diffForHumans(),
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    // public function merchant()
    // {
    //     // merchant inverted hasOneThrough user
    //     return $this->hasOneThrough(Merchant::class, User::class, 'id', 'id', 'user_id', 'merchant_id');
    // }

    public function store()
    {
        return $this->belongsTo(Store::class);
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
        $query->where('flash_deal', true);
    }

    /**
     * claimed_quantity
     */
    public function getClaimedQuantityAttribute()
    {
        return $this->claims()->wherePivot('status', self::CLAIM_SUCCESS)->count();
    }

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
