<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Merchant extends BaseModel implements HasMedia, Auditable
{
    use HasFactory, InteractsWithMedia, \OwenIt\Auditing\Auditable, Searchable;

    const MEDIA_COLLECTION_NAME = 'merchant_logos';
    const MEDIA_COLLECTION_NAME_PHOTOS = 'merchant_photos';

    const STATUS = [
        0 => 'Pending',
        1 => 'Approved',
        2 => 'Rejected'
    ];

    const STATUS_PENDING = 0;
    const STATUS_APPROVED = 1;
    const STATUS_REJECTED = 2;


    /**
     * Search Setup
     */
    public function searchableAs(): string
    {
        return config('scout.prefix').'merchants_index';
    }

    public function toSearchableArray()
    {
        // get stores related to this merchhant
        if ($this->stores()->count() > 0) {
            $this->stores->load('state', 'country');
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'business_name' => $this->business_name,
            'logo' => ($this->getFirstMediaUrl(self::MEDIA_COLLECTION_NAME)) ? $this->getFirstMediaUrl(self::MEDIA_COLLECTION_NAME) : null,
            'photos' => $this->getMedia(self::MEDIA_COLLECTION_NAME_PHOTOS)->map(function ($item) {
                return $item->getFullUrl();
            }),
            'address' => $this->address,
            'address_postcode' => $this->address_postcode,
            'state' => $this->state,
            'country' => $this->country,
            'status' => $this->status,
            'stores' => $this->stores->map(function ($store) {
                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'manager_name' => $store->manager_name,
                    'business_phone_no' => $store->business_phone_no,
                    'business_hours' => $store->business_hours,
                    'address' => $store->address,
                    'address_postcode' => $store->address_postcode,
                    'lat' => $store->lat,
                    'lng' => $store->lng,
                    'is_hq' => $store->is_hq,
                    'state' => $store->state,
                    'country' => $store->country,
                    'created_at' => $store->created_at,
                    'updated_at' => $store->updated_at,
                ];
            }),
            'created_at' => $this->created_at,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        // only if published and is public is searcheable
        return $this->status === self::STATUS_APPROVED;
    }


    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function categories()
    {
        return $this->belongsToMany(MerchantCategory::class, 'merchant_category_merchants')
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

    public function stores()
    {
        // hasMany through users.id
        return $this->hasManyThrough(Store::class, User::class, 'merchant_id', 'user_id', 'id', 'id');
    }

    public function scopeApproved(Builder $query): void
    {
        $query->where($this->getTable() . '.status', self::STATUS_APPROVED);
    }
}
