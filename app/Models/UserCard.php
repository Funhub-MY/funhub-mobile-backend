<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCard extends Model
{
    use HasFactory;

    protected $table = 'user_cards';

    protected $guarded = ['id'];

    protected $appends = ['is_expired'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getIsExpiredAttribute()
    {
        $cardExpiryMonth = $this->card_expiry_month;
        $cardExpiryYear = $this->card_expiry_year;
        $now = now();

        if ($cardExpiryMonth && $cardExpiryYear) {
            $cardExpiryDate = Carbon::createFromFormat('mY', $cardExpiryMonth . '-' . $cardExpiryYear);
            if ($cardExpiryDate->isPast()) {
                return true;
            }
        }
        return false;
    }
}
