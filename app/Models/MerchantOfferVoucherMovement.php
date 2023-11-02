<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantOfferVoucherMovement extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id'
    ];

    public function fromMerchantOffer()
    {
        return $this->belongsTo(MerchantOffer::class, 'from_merchant_offer_id');
    }

    public function toMerchantOffer()
    {
        return $this->belongsTo(MerchantOffer::class, 'to_merchant_offer_id');
    }

    public function voucher()
    {
        return $this->belongsTo(MerchantOfferVoucher::class, 'voucher_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
