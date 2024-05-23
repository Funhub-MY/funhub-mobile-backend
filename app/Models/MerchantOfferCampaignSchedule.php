<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantOfferCampaignSchedule extends Model
{
    use HasFactory;

    const STATUS = [
        0 => 'Draft',
        1 => 'Published',
        2 => 'Archived'
    ];

    const STATUS_DRAFT = 0;
    const STATUS_PUBLISHED = 1;
    const STATUS_ARCHIVED = 2;

    protected $guarded = ['id'];

    protected $table = 'merchant_offer_campaigns_schedules';

    public function campaign()
    {
        return $this->belongsTo(MerchantOfferCampaign::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
