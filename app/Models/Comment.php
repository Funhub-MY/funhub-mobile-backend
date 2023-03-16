<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    const STATUS = [
        0 => 'Draft',
        1 => 'Published',
        2 => 'Hidden'
    ];

    // filterable columns for frontend filtering
    const FILTERABLE = [
        'id',
        'commentable_id',
        'commentable_type',
        'user_id',
        'status',
        'created_at',
        'updated_at'
    ];

    protected $guarded = ['id'];

    public function commentable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reports()
    {
        return $this->morphMany(Report::class, 'reportable');
    }
}
