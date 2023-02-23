<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Interaction extends Model
{
    use HasFactory;

    const STATUS = [
        0 => 'Draft',
        1 => 'Published',
        2 => 'Hidden'
    ];

    const TYPE = [
        0 => 'Like',
        1 => 'Dislike',
        2 => 'Share',
    ];

    protected $guarded = ['id'];

    public function interactable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
