<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantOfferClaim extends Model
{
    use HasFactory;

    protected $table = 'merchant_offer_user';

    protected $guarded = ['id'];

    protected $appends = ['status_label'];

    const CLAIM_SUCCESS = 1;
    const CLAIM_FAILED = 2;
    const CLAIM_AWAIT_PAYMENT = 3;
    const CLAIM_STATUS = [
        self::CLAIM_SUCCESS => 'Success',
        self::CLAIM_FAILED => 'Failed',
        self::CLAIM_AWAIT_PAYMENT => 'Awaiting Payment'
    ];

    public function merchantOffer()
    {
        return $this->belongsTo(MerchantOffer::class, 'merchant_offer_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function redeem()
    {
        return $this->hasOne(MerchantOfferClaimRedemptions::class, 'claim_id', 'id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::CLAIM_STATUS[$this->status];
    }
}
