<?php

namespace App\Models;

use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Campaign extends BaseModel implements HasMedia, Auditable
{
    use HasFactory, InteractsWithMedia, \OwenIt\Auditing\Auditable;

    protected $guarded = ['id'];

    const MEDIA_COLLECTION_NAME = 'campaign_gallery';
    const BANNER_COLLECTION = 'campaign_banners';
    const ICON_COLLECTION = 'campaign_icons';
    const EVENT_COLLECTION = 'campaign_event_banners';

    public function questions()
    {
        return $this->hasMany(CampaignQuestion::class, 'campaign_id');
    }

    public function activeQuestionsByBrand()
    {
        return $this->hasMany(CampaignQuestion::class, 'campaign_id')
            ->where('is_active', true)
            ->groupBy('brand');
    }

    public function respondantDetails()
    {
        return $this->hasMany(CampaignRespondantDetail::class, 'campaign_id');
    }
}
