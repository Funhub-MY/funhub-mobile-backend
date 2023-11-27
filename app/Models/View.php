<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class View extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $cacheCooldownSeconds = 300; // 5 minutes

    protected $guarded = ['id'];

    protected $table = 'views';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function viewable()
    {
        return $this->morphTo();
    }
}
