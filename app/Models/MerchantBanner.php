<?php

namespace App\Models;

use App\Models\BaseModel;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MerchantBanner extends BaseModel implements HasMedia, Auditable
{
    use HasFactory, InteractsWithMedia, \OwenIt\Auditing\Auditable;

    const MEDIA_COLLECTION_NAME = 'merchant_banner';

    const STATUS_DRAFT = 1;
    const STATUS_PUBLISHED = 2;
    const STATUS_ARCHIVED = 3;

    const STATUS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_PUBLISHED => 'Published',
        self::STATUS_ARCHIVED => 'Archived'
    ];

    protected $guarded = [
        'id'
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::MEDIA_COLLECTION_NAME)
            ->singleFile();
    }
}
