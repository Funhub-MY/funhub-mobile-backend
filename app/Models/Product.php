<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Product extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    const MEDIA_COLLECTION_NAME = 'product_images';

    protected $guarded = ['id'];

    protected $appends = ['thumbnail'];

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
}
