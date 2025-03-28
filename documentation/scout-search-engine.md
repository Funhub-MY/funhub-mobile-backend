# Scout Search Engine Implementation

The FunHub Mobile Backend leverages Laravel Scout with Algolia as the search engine provider to deliver powerful, scalable, and efficient search capabilities across multiple models. This document outlines the implementation details, configuration, and best practices for the Scout search integration.

## Overview

Laravel Scout provides a driver-based solution for adding full-text search to Eloquent models. In the FunHub Mobile Backend, Scout is configured with Algolia to provide:

- Full-text search across multiple models
- Geolocation-based search
- Faceted search and filtering
- Real-time indexing
- Custom ranking and relevance

## Models Using Scout

The following key models in the FunHub Mobile Backend implement Scout for search capabilities:

### 1. Article Model

Articles are a core content type that users can search for based on various criteria including title, body content, location, categories, and tags.

```php
use Laravel\Scout\Searchable;

class Article extends BaseModel implements HasMedia, Auditable
{
    use HasFactory, InteractsWithMedia, Searchable, \OwenIt\Auditing\Auditable;
    
    // ...
}
```

#### Key Search Features:

- **Index Name**: `{scout.prefix}articles_index`
- **Searchable Fields**: 
  - Basic: title, body, type, status
  - Relationships: categories, tags, user
  - Metadata: creation date, publication date
  - Location: coordinates, city, state
  - Engagement metrics: comments, likes, views counts
- **Geolocation Support**: Uses `_geoloc` field for location-based searches
- **Conditional Indexing**: Only published, public, non-expired articles are indexed
  ```php
  public function shouldBeSearchable(): bool
  {
      return $this->status === self::STATUS_PUBLISHED && 
             $this->visibility === self::VISIBILITY_PUBLIC && 
             $this->is_expired == false;
  }
  ```
- **Related Content**: Includes related stores and merchant offers in the search index

### 2. Store Model

Stores represent physical locations that users can search for based on name, location, categories, and available offers.

```php
use Laravel\Scout\Searchable;

class Store extends BaseModel implements HasMedia, Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable, Searchable, InteractsWithMedia;
    
    // ...
}
```

#### Key Search Features:

- **Index Name**: `{scout.prefix}stores_index`
- **Searchable Fields**:
  - Basic: name, address, business hours
  - Relationships: categories, merchant, articles
  - Metadata: creation date, status
  - Ratings: average rating score
- **Geolocation Support**: Uses `_geoloc` field with lat/lng coordinates
- **Conditional Indexing**: Only active stores with approved merchants are indexed
  ```php
  public function shouldBeSearchable(): bool
  {
      if ($this->user_id) {
          // if has user_id, then make sure only approved merchant is searchable
          if ($this->merchant) {
              return $this->merchant->status === Merchant::STATUS_APPROVED && 
                     $this->status === self::STATUS_ACTIVE;
          }
      }
      
      // unonboarded merchants do not have user_id, make them searcheable
      return $this->status !== self::STATUS_ARCHIVED;
  }
  ```
- **Merchant Offers**: Includes available merchant offers in the search index

### 3. MerchantOffer Model

Merchant offers represent deals, vouchers, and promotions that users can search for based on various criteria.

```php
use Laravel\Scout\Searchable;

class MerchantOffer extends BaseModel implements HasMedia, Auditable
{
    use HasFactory, InteractsWithMedia, Searchable, \OwenIt\Auditing\Auditable;
    
    // ...
}
```

#### Key Search Features:

- **Index Name**: `{scout.prefix}merchant_offers_index`
- **Searchable Fields**:
  - Basic: name, SKU, description, price
  - Relationships: merchant, stores, categories
  - Availability: available_at, available_until dates
  - Pricing: unit_price, point_fiat_price, discounted prices
  - Inventory: quantity, claimed_quantity
- **Geolocation Support**: Includes store locations for geo-based searches
- **Conditional Indexing**: Only published offers are indexed
  ```php
  public function shouldBeSearchable(): bool
  {
      return $this->status === self::STATUS_PUBLISHED;
  }
  ```
- **Scoring**: Includes custom scoring based on ratings and discount rates

## Indexing Process

### Automatic Indexing

By default, Scout automatically indexes models when they are created, updated, or deleted. This behavior is built into the Searchable trait.

### Manual Indexing

For bulk operations or when automatic indexing is disabled, manual indexing can be performed:

```php
// Index a single model
$article->searchable();

// Index a collection of models
Article::where('status', Article::STATUS_PUBLISHED)->searchable();

// Index all models
Article::all()->searchable();
```

### Scheduled Re-indexing

The IndexStore job is used to update store search indices when ratings are updated:

```php
// Dispatched after new ratings are created
dispatch(new IndexStore($store));
```

This ensures that the average ratings displayed in search results are always current.

## Performance Optimization

### 1. Selective Indexing

Only essential models and records are indexed to minimize index size and improve performance:

- Articles: Only published, public, non-expired articles
- Stores: Only active stores with approved merchants
- Merchant Offers: Only published offers

### 2. Chunked Indexing

For large datasets, indexing is performed in chunks to prevent memory issues:

```php
Article::where('status', Article::STATUS_PUBLISHED)
    ->chunkById(500, function ($articles) {
        $articles->searchable();
    });
```

### 3. Queue-based Indexing

Scout operations are queued to prevent blocking the main application thread:

```php
// In config/scout.php
'queue' => [
    'queue' => 'scout',
    'connection' => env('SCOUT_QUEUE_CONNECTION', 'redis'),
],
```

### 4. Field Optimization

Search fields are carefully selected to balance search quality and performance:

- Long text fields (like article body) are truncated to reduce index size
- Unnecessary fields are excluded from the index
- Relationships are selectively included based on search requirements

## Geolocation Search

Geolocation search is implemented for Articles, Stores, and MerchantOffers using Algolia's `_geoloc` field:

```php
'_geoloc' => [
    'lat' => floatval($this->location->first()->lat),
    'lng' => floatval($this->location->first()->lng)
]
```

This enables radius-based searches like:

```php
$results = Article::search('query')
    ->aroundLatLng($lat, $lng)
    ->aroundRadius($radiusInMeters)
    ->get();
```

## Custom Ranking and Relevance

### Store Ratings

Store ratings are included in the search index and used for ranking:

```php
'ratings' => $this->storeRatings->avg('rating'),
```

The IndexStore job ensures that ratings are updated in both the database and search index:

```php
$store->update([
    'ratings' => $averageRating
]);
$store->searchable();
```

### Merchant Offer Scoring

Merchant offers use a custom scoring system based on ratings and discount rates:

```php
// Calculate discount rate
$discountRate = round((($this->point_fiat_price - $this->discounted_point_fiat_price) / $this->point_fiat_price) * 100, 1);

// Calculate final score (ratings + discount rate)
$score = $ratings + $discountRate;
```

## Configuration

### Scout Configuration

The Scout configuration is defined in `config/scout.php`:

```php
return [
    'driver' => env('SCOUT_DRIVER', 'algolia'),
    'prefix' => env('SCOUT_PREFIX', ''),
    'queue' => [
        'queue' => 'scout',
        'connection' => env('SCOUT_QUEUE_CONNECTION', 'redis'),
    ],
    'after_commit' => false,
    'chunk' => [
        'searchable' => 500,
        'unsearchable' => 500,
    ],
    'soft_delete' => false,
    'identify' => env('SCOUT_IDENTIFY', false),
    'algolia' => [
        'id' => env('ALGOLIA_APP_ID', ''),
        'secret' => env('ALGOLIA_SECRET', ''),
    ],
];
```

### Environment Variables

Key environment variables for Scout configuration:

```
SCOUT_DRIVER=algolia
SCOUT_PREFIX=funhub_
ALGOLIA_APP_ID=your_app_id
ALGOLIA_SECRET=your_secret_key
SCOUT_QUEUE_CONNECTION=redis
```

## Best Practices

1. **Use Selective Indexing**: Only index models and records that need to be searchable
2. **Optimize Index Size**: Include only necessary fields in the search index
3. **Use Queue-based Indexing**: Prevent blocking the main application thread
4. **Update Indices in Real-time**: Keep search indices up-to-date with database changes
5. **Implement Conditional Indexing**: Use `shouldBeSearchable()` to control which records are indexed
6. **Test Search Performance**: Regularly test search performance with various queries and datasets

## Troubleshooting

### Common Issues

1. **Missing Records**: If records are missing from search results, check:
   - The `shouldBeSearchable()` method to ensure records meet indexing criteria
   - The indexing process to ensure records are being indexed
   - The search query to ensure it matches the indexed fields

2. **Slow Search Performance**: If search is slow, consider:
   - Reducing the number of fields in the search index
   - Optimizing the search query
   - Increasing Algolia plan limits

3. **Inconsistent Results**: If search results are inconsistent with database state:
   - Ensure indices are updated when records change
   - Manually re-index affected records
   - Check for queue processing issues

### Manual Re-indexing

To manually re-index all searchable models (warning this will wipe all indexed data):

```bash
php artisan scout:import "App\Models\Article"
php artisan scout:import "App\Models\Store"
php artisan scout:import "App\Models\MerchantOffer"
```

## Integration with Frontend

The search functionality is exposed through API endpoints that accept various search parameters:

- Text queries for full-text search
- Filters for narrowing results by specific criteria
- Geolocation parameters for location-based search
- Pagination parameters for handling large result sets

## Conclusion

The Scout search engine implementation in the FunHub Mobile Backend provides powerful search capabilities across multiple models. By leveraging Algolia's features and optimizing the indexing process, the system delivers fast, relevant search results while maintaining good performance.
