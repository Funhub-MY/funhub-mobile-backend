<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rsvp extends Model
{
    protected $table = 'rsvp_users';

    protected $fillable = [
        'name',
        'email',
        'phone_no',
    ];

    public $timestamps = false;

    use HasFactory;
}
