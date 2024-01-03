<?php

namespace App\Models;

use App\Models\BaseModel;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Merchant extends BaseModel implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    const MEDIA_COLLECTION_NAME = 'merchant_logos';

    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
