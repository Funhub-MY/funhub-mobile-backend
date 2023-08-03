<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function created_by()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
