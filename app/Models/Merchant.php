<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Merchant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'business_name',
        'business_phone_no',
        'address',
        'address_postcode',
        'pic_name',
        'pic_phone_no',
        'pic_email',
        'user_id',
        'state_id',
        'country_id',
        'deleted_at',
        'created_at',
        'updated_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
