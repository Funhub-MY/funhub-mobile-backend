<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'user_id',
        'merchant_id',
        'store_id',
        'description',
        'unit_price',
        'available_at',
        'available_until',
        'quantity',
        'sku',
        'claimed',
        'deleted_at',
        'created_at',
        'updated_at'
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

    public function claimed_by_users()
    {
        return $this->belongsToMany(User::class, 'merchant_offer_user')->withPivot('status');
    }
}
