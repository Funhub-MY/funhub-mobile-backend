<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserHistoricalLocation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $appends = ['full_address'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // getter full address
    public function getFullAddressAttribute()
    {
        return $this->address . ', ' . $this->address_2 . ', ' . $this->zip_code . ', ' . $this->city . ', ' . $this->state . ', ' . $this->country;
    }
}
