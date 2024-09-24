<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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

        if ($cardExpiryMonth && $cardExpiryYear) {
            // Ensure month is zero-padded to two digits
            $cardExpiryMonth = str_pad($cardExpiryMonth, 2, '0', STR_PAD_LEFT);

            // Create date string in the format 'm/Y'
            $dateString = $cardExpiryMonth . '/' . $cardExpiryYear;
            $cardExpiryDate = Carbon::createFromFormat('m/Y', $dateString);
            $cardExpiryDate->endOfMonth();

            return $cardExpiryDate->isPast();
        }

        return true;
    }

    public function scopeNotExpired(Builder $query): void
    {
        $query->where('card_expiry_month', '<=', now()->month)
            ->where('card_expiry_year', '<=', now()->year);
    }
}
