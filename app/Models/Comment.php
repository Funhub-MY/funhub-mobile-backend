<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends BaseModel
{
    use \Staudenmeir\EloquentEagerLimit\HasEagerLimit;

    // protected $cacheCooldownSeconds = 300; // 5 minutes

    use HasFactory;

    const STATUS = [
        0 => 'Draft',
        1 => 'Published',
        2 => 'Hidden'
    ];

    const STATUS_DRAFT = 0;
    const STATUS_PUBLISHED = 1;
    const STATUS_HIDDEN = 2;

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
        return $this->morphMany(Reports::class, 'reportable');
    }

    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    public function likes()
    {
        return $this->hasMany(CommentLike::class);
    }

    public function views()
    {
        return $this->morphMany(View::class, 'viewable');
    }

    /**
     * Scopes
     */
    public function scopePublished()
    {
        return $this->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeDraft()
    {
        return $this->where('status', self::STATUS_DRAFT);
    }

    public function scopeHidden()
    {
        return $this->where('status', self::STATUS_HIDDEN);
    }
}
