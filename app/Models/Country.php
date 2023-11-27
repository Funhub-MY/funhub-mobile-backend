<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id'];

    public function states()
    {
        return $this->hasMany(State::class);
    }
}
