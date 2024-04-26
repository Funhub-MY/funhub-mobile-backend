<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function names()
    {
        return $this->hasMany(CityName::class);
    }

    public function locations()
    {
        return $this->hasMany(Location::class);
    }
}
