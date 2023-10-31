<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Netsells\GeoScope\Traits\GeoScopeTrait;

class Location extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, GeoScopeTrait;

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

    /**
     * Scope a query to only include locations within a given distance.
     */
    public function scopeWithinKmOf($query, $latitude, $longitude, $km)
    {
        $box = static::boundingBox($latitude, $longitude, $km / 1000);
        // Convert the latitudes to tranches
        $box['minLat'] = (int)floor($box['minLat'] * 1000);
        $box['maxLat'] = (int)ceil($box['maxLat'] * 1000);

        // Fill out the range of possibilities.
        $lats = range($box['minLat'], $box['maxLat']);

        Log::info('box', [
            'box' => $box,
            'lats' => $latitude,
            'lng' => $longitude,
            'km' => $km,
        ]);

        $query
            // Latitude part of the bounding box.
            ->whereIn('lat_1000_floor', $lats)
            // Longitude part of the bounding box.
            ->whereBetween('lng', [
                $box['minLon'],
                $box['maxLon']
            ])
            // Accurate calculation that eliminates false positives.
            ->whereRaw('(ST_Distance_Sphere(point(lng, lat), point(?,?))) <= ?', [
                $longitude,
                $latitude,
                $km
            ]);
    }

    /**
     * Get the bounding box of a location.
     *
     * @param float $latitude
     * @param float $longitude
     * @param float $distance
     * @return float
     */
    public static function boundingBox($latitude, $longitude, $distance)
    {
        $latLimits = [deg2rad(-90), deg2rad(90)];
        $lonLimits = [deg2rad(-180), deg2rad(180)];

        $radLat = deg2rad($latitude);
        $radLon = deg2rad($longitude);

        if ($radLat < $latLimits[0] || $radLat > $latLimits[1]
            || $radLon < $lonLimits[0] || $radLon > $lonLimits[1]) {
            throw new \Exception("Invalid Argument");
        }

        // Angular distance in radians on a great circle,
        // using Earth's radius in km.
        $angular = $distance / 6371;

        $minLat = $radLat - $angular;
        $maxLat = $radLat + $angular;

        if ($minLat > $latLimits[0] && $maxLat < $latLimits[1]) {
            $deltaLon = asin(sin($angular) / cos($radLat));
            $minLon = $radLon - $deltaLon;

            if ($minLon < $lonLimits[0]) {
                $minLon += 2 * pi();
            }

            $maxLon = $radLon + $deltaLon;

            if ($maxLon > $lonLimits[1]) {
                $maxLon -= 2 * pi();
            }
        } else {
            // A pole is contained within the distance.
            $minLat = max($minLat, $latLimits[0]);
            $maxLat = min($maxLat, $latLimits[1]);
            $minLon = $lonLimits[0];
            $maxLon = $lonLimits[1];
        }

        return [
            'minLat' => rad2deg($minLat),
            'minLon' => rad2deg($minLon),
            'maxLat' => rad2deg($maxLat),
            'maxLon' => rad2deg($maxLon),
        ];
    }
}
