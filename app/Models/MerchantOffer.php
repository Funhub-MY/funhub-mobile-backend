<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Builder;

class MerchantOffer extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, Searchable;

    const MEDIA_COLLECTION_NAME = 'merchant_offer_gallery';

    protected $guarded = [
        'id'
    ];

    protected $appends = [
        'claimed_quantity'
    ];

    const STATUS = [
        0 => 'Draft',
        1 => 'Published',
    ];

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

    /**
     * Search Setup
     */
    public function searchableAs(): string
    {
        return 'merchant_offers_index';
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


    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

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

    public function claims()
    {
        return $this->belongsToMany(User::class, 'merchant_offer_user')
            ->withPivot('status', 'order_no', 'tax', 'discount', 'net_amount', 'remarks')
            ->withTimestamps();
    }

    public function categories()
    {
        return $this->belongsToMany(MerchantCategory::class, 'merchant_category_merchant_offer')
            ->withTimestamps();
    }

    /**
     * Scope a query to only include published offers.
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 1);
    }

    /**
     * claimed_quantity
     */
    public function getClaimedQuantityAttribute()
    {
        return $this->claims()->wherePivot('status', self::CLAIM_SUCCESS)->count();
    }
}
