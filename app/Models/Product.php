<?php

namespace App\Models;

use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Builder;

class Product extends BaseModel implements HasMedia, Auditable
{
    use HasFactory, InteractsWithMedia, \OwenIt\Auditing\Auditable;

    const STATUS_DRAFT = 0;
    const STATUS_PUBLISHED = 1;
    const STATUS_ARCHIVED = 2;

    const STATUS = [
        0 => 'Draft',
        1 => 'Published',
        2 => 'Archived'
    ];

    const TYPES = [
        'normal' => 'Gift Card',
        'limited' => 'Limited Card with Campaign',
    ];

    const MEDIA_COLLECTION_NAME = 'product_images';

    protected $guarded = ['id'];

    protected $appends = ['thumbnail'];

    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    public function created_by()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updated_by()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function getThumbnailAttribute()
    {
        return $this->getFirstMediaUrl(self::MEDIA_COLLECTION_NAME, 'thumb');
    }

    public function rewards()
    {
        return $this->belongsToMany(Reward::class, 'product_reward')
            ->withPivot('quantity');
    }

    /**
     * Scope a query to only include published offers.
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 1);
    }

    /**
     * Scope a query to only include normal offers.
     */
    public function scopeNormal(Builder $query): void
    {
        $query->where('type', 'normal');
    }

    /**
     * Scope a query to only include limited offers.
     */
    public function scopeLimited(Builder $query): void
    {
        $query->where('type', 'limited');
    }
}
