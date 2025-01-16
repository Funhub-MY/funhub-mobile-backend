<?php

namespace App\Models;

use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    const STATUS = [
        0 => 'Pending',
        1 => 'Success',
        2 => 'Failed'
    ];

    const STATUS_PENDING = 0;
    const STATUS_SUCCESS = 1;
    const STATUS_FAILED = 2;

    protected $guarded = ['id'];

    public function transactionable()
    {
        return $this->morphTo('transactionable');
    }

    public function created_by()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'transactionable_id');
    }
}
