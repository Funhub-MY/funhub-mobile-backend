<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FailedStoreImport extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'address',
        'address_postcode',
        'city',
        'state_id',
        'country_id',
        'business_phone_no',
        'business_hours',
        'rest_hours',
        'is_appointment_only',
        'user_id',
        'merchant_id',
        'google_place_id',
        'lang',
        'long',
        'parent_categories',
        'sub_categories',
        'is_hq',
        'failure_reason',
        'original_data',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'business_hours' => 'json',
        'rest_hours' => 'json',
        'is_appointment_only' => 'boolean',
        'is_hq' => 'boolean',
        'original_data' => 'json',
    ];
}
