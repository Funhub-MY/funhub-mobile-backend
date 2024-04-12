<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantOfferCampaignSchedule extends Model
{
    use HasFactory;

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
