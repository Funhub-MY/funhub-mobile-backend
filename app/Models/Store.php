<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'business_phone_no',
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
}
