<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'tel_no',
        'company_name',
        'business_type',
        'is_others',
        'message_type',
        'message',
        'created_by',
    ];
}
