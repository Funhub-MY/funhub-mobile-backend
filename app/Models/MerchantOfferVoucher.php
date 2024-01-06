<?php

namespace App\Models;

use App\Models\BaseModel;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * MerchantOfferVoucher
 * Represents individual voucher for a merchant offer (will affect quantity count of merchant offer)
 */
class MerchantOfferVoucher extends BaseModel implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $table = 'merchant_offer_vouchers';

    protected $guarded = ['id'];

    protected $appends = ['voucher_redeemed'];

    public function merchant_offer()
    {
        return $this->belongsTo(MerchantOffer::class, 'merchant_offer_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owned_by_id');
    }

    public function claim()
    {
        return $this->hasOne(MerchantOfferClaim::class, 'voucher_id');
    }

    public function latestSuccessfulClaim()
    {
        return $this->hasOne(MerchantOfferClaim::class, 'voucher_id')->where('status', MerchantOfferClaim::CLAIM_SUCCESS)->latest();
    }

    public function redeem()
    {
        return $this->hasOneThrough(MerchantOfferClaimRedemptions::class, MerchantOfferClaim::class, 'voucher_id', 'claim_id', 'id', 'id');
    }

    public static function generateCode()
    {
        return  strtoupper(date('Y').Str::random(4).random_int(10, 99)); // 2023ABCD99
    }

    public function scopeClaimed(Builder $query): void
    {
         $query->whereNotNull('owned_by_id');
    }

    public function scopeUnclaimed(Builder $query): void
    {
         $query->whereNull('owned_by_id');
    }

    public function getVoucherRedeemedAttribute()
    {
        if ($this->claim && $this->redeem()->exists()) {
            return true;
        }
        return false;
    }
}
