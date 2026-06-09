<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Location;
use App\Models\Store;
use Illuminate\Support\Facades\Log;

class LocationStoreLinker
{
    /**
     * Store already linked to this location (prefers listed + onboarded).
     */
    public static function findLinkedStore(Location $location, bool $activeOnly = false): ?Store
    {
        $query = Store::whereHas('location', function ($q) use ($location) {
            $q->where('locations.id', $location->id);
        });

        if ($activeOnly) {
            $query->where('status', Store::STATUS_ACTIVE);
        }

        return $query
            ->orderByRaw('CASE WHEN stores.status = ? THEN 0 ELSE 1 END', [Store::STATUS_ACTIVE])
            ->orderByRaw('CASE WHEN stores.merchant_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByDesc('stores.id')
            ->first();
    }

    public static function findActiveStoreByName(string $name): ?Store
    {
        return Store::where('name', $name)
            ->where('status', Store::STATUS_ACTIVE)
            ->orderByRaw('CASE WHEN merchant_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByDesc('id')
            ->first();
    }

    public static function attachLocationIfMissing(Store $store, Location $location): void
    {
        if (!$store->location()->where('locations.id', $location->id)->exists()) {
            $store->location()->attach($location->id);
            Log::info('[LocationStoreLinker] Linked store to location', [
                'store_id' => $store->id,
                'location_id' => $location->id,
            ]);
        }
    }

    public static function resolveStoreStatusFromLocationName(string $locationName): int
    {
        $normalized = trim(strtolower($locationName));

        if (str_starts_with($normalized, 'lorong')
            || str_starts_with($normalized, 'jalan')
            || str_starts_with($normalized, 'street')) {
            return Store::STATUS_INACTIVE;
        }

        return Store::STATUS_ACTIVE;
    }

    /**
     * Find an existing store for a location or create one. Avoids duplicate stores/links.
     */
    public static function resolveOrCreateForLocation(Location $location, ?Article $article = null): ?Store
    {
        $activeLinked = self::findLinkedStore($location, true);
        if ($activeLinked) {
            return $activeLinked;
        }

        $activeByName = self::findActiveStoreByName($location->name);
        if ($activeByName) {
            self::attachLocationIfMissing($activeByName, $location);

            return $activeByName;
        }

        $anyLinked = self::findLinkedStore($location, false);
        if ($anyLinked) {
            Log::info('[LocationStoreLinker] Reusing existing linked store (may be unlisted)', [
                'store_id' => $anyLinked->id,
                'location_id' => $location->id,
                'status' => $anyLinked->status,
            ]);

            return $anyLinked;
        }

        $inactiveByName = Store::where('name', $location->name)
            ->orderByDesc('id')
            ->first();

        if ($inactiveByName) {
            self::attachLocationIfMissing($inactiveByName, $location);

            return $inactiveByName;
        }

        $status = self::resolveStoreStatusFromLocationName($location->name);

        $store = Store::create([
            'user_id' => null,
            'name' => $location->name,
            'manager_name' => null,
            'business_phone_no' => null,
            'business_hours' => null,
            'address' => $location->full_address,
            'address_postcode' => $location->zip_code,
            'lang' => $location->lat,
            'long' => $location->lng,
            'is_hq' => false,
            'state_id' => $location->state_id,
            'country_id' => $location->country_id,
            'status' => $status,
        ]);

        self::attachLocationIfMissing($store, $location);

        Log::info('[LocationStoreLinker] Created store for location', [
            'store_id' => $store->id,
            'location_id' => $location->id,
            'status' => $status,
            'article_id' => $article?->id,
        ]);

        return $store;
    }
}
