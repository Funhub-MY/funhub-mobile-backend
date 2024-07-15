<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfferLimitWhitelist extends Model
{
    use HasFactory;

    protected $table = 'offer_limit_whitelists';

    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function campaign()
    {
        return $this->belongsTo(MerchantOfferCampaign::class, 'campaign_id');
    }
}
