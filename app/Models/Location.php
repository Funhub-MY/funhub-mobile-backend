<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Location extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $guarded = ['id'];

    protected $appends = ['full_address'];

    const MEDIA_COLLECTION_NAME = 'location_images';
    const STATUS_PUBLISHED = 1;
    const STATUS_DRAFT = 0;

    public function getLocationAttribute(): array
    {
        return [
            "lat" => (float)$this->lat,
            "lng" => (float)$this->lng,
        ];
    }

    public function setLocationAttribute(?array $location): void
    {
        if (is_array($location))
        {
            $this->attributes['lat'] = $location['lat'];
            $this->attributes['lng'] = $location['lng'];
            unset($this->attributes['location']);
        }
    }

    public static function getLatLngAttributes(): array
    {
        return [
            'lat' => 'lat',
            'lng' => 'lng',
        ];
    }

    public static function getComputedLocation(): string
    {
        return 'location';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function locatable()
    {
        return $this->morphTo();
    }

    public function articles()
    {
        return $this->morphedByMany(Article::class, 'locatable');
    }

    public function merchantOffers()
    {
        return $this->morphedByMany(MerchantOffer::class, 'locatable');
    }


    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function ratings()
    {
        return $this->hasMany(LocationRating::class);
    }

    public function scopePublished(Builder $query): void
    {
         $query->where('status', self::STATUS_PUBLISHED);
    }

    public function getFullAddressAttribute(): string
    {
        return $this->address . ', ' . $this->city . ', ' . $this->state->name . ', ' . $this->country->name;
    }
}
