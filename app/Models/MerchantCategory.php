<?php

namespace App\Models;

use App\Models\BaseModel;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\HasMedia;

class MerchantCategory extends BaseModel implements HasMedia, Auditable
{
    use HasFactory, InteractsWithMedia, \OwenIt\Auditing\Auditable;

    protected $guarded = [
        'id'
    ];

    // filterables
    const FILTERABLE = [
        'id',
        'name',
        'created_at',
        'updated_at'
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function merchants()
    {
        return $this->belongsToMany(Merchant::class, 'merchant_category_merchants')
                ->withTimestamps();
    }

    public function offer()
    {
        return $this->belongsToMany(MerchantOffer::class, 'merchant_category_merchant_offer')
                ->withTimestamps();
    }

    public function availableOffers()
    {
        return $this->belongsToMany(MerchantOffer::class, 'merchant_category_merchant_offer')
                ->where('status', MerchantOffer::STATUS_PUBLISHED)
                ->where('available_at', '<=', now())
                ->withTimestamps();
    }
}
