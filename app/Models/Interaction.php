<?php

namespace App\Models;

use App\Models\BaseModel;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Interaction extends BaseModel implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $cacheCooldownSeconds = 300; // 5 minutes

    const STATUS = [
        0 => 'Draft',
        1 => 'Published',
        2 => 'Hidden'
    ];

    const STATUS_DRAFT = 0;
    const STATUS_PUBLISHED = 1;
    const STATUS_HIDDEN = 2;

    const TYPE_LIKE = 1;
    const TYPE_DISLIKE = 2;
    const TYPE_SHARE = 3;
    const TYPE_BOOKMARK = 4;

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

    protected $appends = ['share_url'];

    public function interactable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shareableLink()
    {
        return $this->belongsToMany(ShareableLink::class, 'interactions_shareable_links', 'interaction_id', 'shareable_link_id');
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

    /**
     * Accessors
     */

     /**
      * Get share_url
      */
    public function getShareUrlAttribute()
    {
        // DEPRECATED
        return null;
    }
}
