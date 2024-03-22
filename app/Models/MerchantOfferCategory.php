<?php

namespace App\Models;

use App\Models\BaseModel;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class MerchantOfferCategory extends BaseModel implements HasMedia, Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable, InteractsWithMedia;

    protected $guarded = ['id'];

    // filterables
    const FILTERABLE = [
        'id',
        'name',
        'created_at',
        'updated_at'
    ];

    public function merchantOffers()
    {
        return $this->belongsToMany(MerchantOffer::class, 'merchant_offer_merchant_offer_categories');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(MerchantOfferCategory::class, 'parent_id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
