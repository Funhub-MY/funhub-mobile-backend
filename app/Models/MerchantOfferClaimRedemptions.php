<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantOfferClaimRedemptions extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'merchant_offer_claims_redemptions';

    public function merchantOffer()
    {
        return $this->belongsTo(MerchantOffer::class, 'merchant_offer_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function claim()
    {
        return $this->belongsTo(MerchantOfferClaim::class, 'claim_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
}
