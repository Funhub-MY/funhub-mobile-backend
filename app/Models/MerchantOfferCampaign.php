<?php

namespace App\Models;

use Illuminate\Console\Concerns\InteractsWithIO;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MerchantOfferCampaign extends BaseModel implements HasMedia, Auditable
{
    use HasFactory, InteractsWithMedia, \OwenIt\Auditing\Auditable;

    protected $guarded = [
        'id'
    ];

	protected $casts = [
		'highlight_messages' => 'array',
	];

    const STATUS = [
        0 => 'Draft',
        1 => 'Published',
        2 => 'Archived'
    ];
    const STATUS_DRAFT = 0;
    const STATUS_PUBLISHED = 1;
    const STATUS_ARCHIVED = 2;

    const MEDIA_COLLECTION_NAME = 'merchant_offer_campaign_gallery';
    const MEDIA_COLLECTION_HORIZONTAL_BANNER = 'merchant_offer_campaign_horizontal_banner';

    // touches offers and stores
    // protected $touches = ['merchantOffers', 'stores'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function merchantOffers()
    {
        return $this->hasMany(MerchantOffer::class);
    }

    public function schedules()
    {
        return $this->hasMany(MerchantOfferCampaignSchedule::class);
    }

    public function upcomingSchedules()
    {
        return $this->schedules()->where('available_at', '<=', now());
    }

    public function pastSchedules()
    {
        return $this->schedules()->where('available_at', '>', now());
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'merchant_offer_campaign_stores', 'merchant_offer_campaign_id', 'store_id')
            ->withTimestamps();
    }


    public function offerCategories()
    {
        return $this->belongsToMany(MerchantOfferCategory::class, 'merchant_offer_categories_merchant_offer_campaigns')
            ->where('parent_id', null)
            ->withTimestamps();
    }

    // NOTE since this is a self-referencing relationship, sync will override offerCategories!
    public function offerSubCategories()
    {
        return $this->belongsToMany(MerchantOfferCategory::class, 'merchant_offer_categories_merchant_offer_campaigns')
            ->where('parent_id', '!=', null)
            ->withTimestamps();
    }

    public function allOfferCategories()
    {
        return $this->belongsToMany(MerchantOfferCategory::class, 'merchant_offer_categories_merchant_offer_campaigns')
            ->withTimestamps();
    }

    public function voucherCodes()
    {
        return $this->hasMany(MerchantOfferCampaignVoucherCode::class, 'merchant_offer_campaign_id');
    }
}
