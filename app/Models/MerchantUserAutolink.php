<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantUserAutolink extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
