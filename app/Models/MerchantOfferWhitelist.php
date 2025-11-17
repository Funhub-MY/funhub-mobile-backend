<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantOfferWhitelist extends Model
{
    use HasFactory;

    protected $table = 'merchant_offer_whitelists';

    protected $guarded = ['id'];

    protected $casts = [
        'override_days' => 'integer',
    ];

    /**
     * Get the merchant offer
     */
    public function merchantOffer()
    {
        return $this->belongsTo(MerchantOffer::class, 'merchant_offer_id');
    }

    /**
     * Get the merchant user (for display purposes)
     */
    public function merchantUser()
    {
        return $this->belongsTo(User::class, 'merchant_user_id');
    }

    /**
     * Get effective days limit for this whitelist entry
     * Returns null if fully whitelisted, or the override_days value
     */
    public function getEffectiveDaysLimit(): ?int
    {
        return $this->override_days;
    }

    /**
     * Check if this offer is fully whitelisted (no restriction)
     */
    public function isFullyWhitelisted(): bool
    {
        return $this->override_days === null;
    }
}
