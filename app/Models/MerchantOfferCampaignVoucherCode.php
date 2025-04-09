<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MerchantOfferCampaignVoucherCode extends BaseModel implements HasMedia, Auditable
{
    use HasFactory, InteractsWithMedia, \OwenIt\Auditing\Auditable;

    protected $table = 'merchant_offer_campaign_voucher_codes';

    protected $fillable = [
        'merchant_offer_campaign_id',
        'voucher_id',
        'code',
        'is_used'
    ];

    const MEDIA_COLLECTION_NAME = 'imported_codes';

    /**
     * Get the campaign that owns the voucher code
     */
    public function campaign()
    {
        return $this->belongsTo(MerchantOfferCampaign::class, 'merchant_offer_campaign_id');
    }

    /**
     * Get the voucher associated with this code
     */
    public function voucher()
    {
        return $this->belongsTo(MerchantOfferVoucher::class, 'voucher_id');
    }
}
