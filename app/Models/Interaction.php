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

    // filterable columns for frontend filtering
    const FILTERABLE = [
        'id',
        'interactable_id',
        'interactable_type',
        'type', // type of intereactions like, dislike, share
        'user_id',
        'status',
        'created_at',
        'updated_at'
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
