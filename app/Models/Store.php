<?php

namespace App\Models;

use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Store extends BaseModel implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable, Searchable;

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

    public function merchant_offers()
    {
        return $this->hasMany(MerchantOffer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function categories()
    {
        return $this->morphToMany(MerchantCategory::class, 'categoryable');
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
}
