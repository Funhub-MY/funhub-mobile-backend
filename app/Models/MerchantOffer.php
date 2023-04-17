<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantOffer extends Model
{
    use HasFactory;

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
    public function scopePublished($query)
    {
        return $query->where('status', 1);
    }

    /**
     * claimed_quantity
     */
    public function getClaimedQuantityAttribute()
    {
        return $this->claims()->wherePivot('status', 'claimed')->count();
    }
}
