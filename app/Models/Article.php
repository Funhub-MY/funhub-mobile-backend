<?php

namespace App\Models;

use App\Models\BaseModel;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class Article extends BaseModel implements HasMedia, Auditable
{
    use HasFactory, InteractsWithMedia, Searchable, \OwenIt\Auditing\Auditable;

    const MEDIA_COLLECTION_NAME = 'article_gallery';

    const STATUS = [
        0 => 'Draft',
        1 => 'Published',
        2 => 'Archived'
    ];

    const STATUS_DRAFT = 0;
    const STATUS_PUBLISHED = 1;
    const STATUS_ARCHIVED = 2;

    const VISIBILITY_PRIVATE = 'private';
    const VISIBILITY_PUBLIC = 'public';

    const TYPE = [
        'multimedia', 'text', 'video'
    ];

    // filterables
    const FILTERABLE = [
        'id',
        'title',
        'type',
        'slug',
        'status',
        'published_at',
        'created_at',
        'updated_at'
    ];

    protected $guarded = ['id'];

    /**
     * Search Setup
     */
    public function searchableAs(): string
    {
        return config('scout.prefix').'articles_index';
    }

    public function toSearchableArray()
    {
        $location = $this->location()->first();
        $city = null;
        $cityNames = [];
        if ($location) {
            $city = $location->city_id ? $location->cityLinked->name : $location->city;

            if ($location->cityLinked) {
                $cityNames = CityName::where('city_id', $location->city_id)->get();
                $cityNames = $cityNames->pluck('name')->toArray();
            } else {
                $cityNames = [$location->city];
            }
        }

        $merchantOffers =  [];
        if ($this->stores) {
            # TODO: optimize this query as N+1
            foreach ($this->stores as $store) {
                foreach ($store->availableMerchantOffers as $offer) {
                    $merchantOffers[] = [
                        'name' => $offer->name,
                        'brand_name' => $offer->merchant->brand_name,
                        'available_at' => $offer->available_at,
                        'available_until' => $offer->available_until,
                    ];
                }
            }
        }

        return [
            'id' => (int) $this->id,
            'title' => $this->title,
            // 'thumbnail' => $this->getFirstMediaUrl(self::MEDIA_COLLECTION_NAME),
            'type' => $this->type,
            'body' => (function($body) {
                $body = mb_convert_encoding($body, 'UTF-8', 'UTF-8');
                $body = strip_tags($body);
                // remove emojis and other non-text characters
                $body = preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $body);
                // remove any remaining non-printable characters except spaces and line breaks
                $body = preg_replace('/[^\P{C}\n ]+/u', '', $body);
                $body = preg_replace('/\s+/', ' ', $body);
                $body = preg_replace('&nbsp;', ' ', $body);

                // truncate to approximately 9,500 bytes to allow room for other fields
                return mb_substr($body, 0, 3166); // Assumes 3 bytes per character on average
            })($this->body),
            'categories' => ($this->categories) ? $this->categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'name_translation' => $category->name_translation,
                ];
            }) : null,
            'tags' => $this->tags->pluck('name'),
            'status' => $this->status,
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'gallery' => $this->getMedia(self::MEDIA_COLLECTION_NAME)->map(function ($media) {
                return $media->getUrl();
            }),
            'comments_count' => $this->comments->count(),
            'likes_count' => $this->interactions->where('type', Interaction::TYPE_LIKE)->count(),
            'dislikes_count' => $this->interactions->where('type', Interaction::TYPE_DISLIKE)->count(),
            'share_count' => $this->interactions->where('type', Interaction::TYPE_SHARE)->count(),
            'bookmarks_count' => $this->interactions->where('type', Interaction::TYPE_BOOKMARK)->count(),
            'views_count' => $this->views->count(),
            // 'count' => [
            //     'comments' => $this->comments->count(),
            //     'likes' => $this->interactions->where('type', Interaction::TYPE_LIKE)->count(),
            //     'dislikes' => $this->interactions->where('type', Interaction::TYPE_DISLIKE)->count(),
            //     'share' => $this->interactions->where('type', Interaction::TYPE_SHARE)->count(),
            //     'bookmarks' => $this->interactions->where('type', Interaction::TYPE_BOOKMARK)->count(),
            //     'views' => $this->views->count()
            // ],
            'location' => ($this->location()->count() > 0) ? [
                'name' => floatval($this->location()->first()->name),
                'city' => $city,
                'state' => ($this->location()->first()->state) ?: null,
                'city_names' => $cityNames,
            ] : null,
            'merchant_offers' => $merchantOffers,
            'stores' => ($this->stores) ? $this->stores->map(function ($store) {
                return [
                    'name' => $store->name,
                    'address' => $store->address,
                    'categories' => ($store->categories) ? $store->categories->map(function ($category) {
                        return [
                            'id' => $category->id,
                            'name' => $category->name,
                            'name_translation' => $category->name_translation,
                        ];
                    }) : null,
                ];
            }) : null,
            '_geoloc' => ($this->location()->count() > 0) ? [
                'lat' => floatval($this->location->first()->lat),
                'lng' => floatval($this->location->first()->lng)
            ] : null,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        // only if published and is public is searcheable
        return $this->status === self::STATUS_PUBLISHED && $this->visibility === self::VISIBILITY_PUBLIC;
    }

    public function calculateInteractionScore()
    {
        $viewCount = $this->views()->where('is_system_generated', false)->count();
        $bookmarkCount = $this->interactions()->where('type', Interaction::TYPE_BOOKMARK)->count();
        $likeCount = $this->interactions()->where('type', Interaction::TYPE_LIKE)->count();
        $commentCount = $this->comments()->count();

        //in future,adjust the weights as needed
        $viewWeight = 1;
        $bookmarkWeight = 1;
        $likeWeight = 1;
        $commentWeight = 1;

        $interactionScore = ($viewCount * $viewWeight) + ($bookmarkCount * $bookmarkWeight) + ($likeCount * $likeWeight) + ($commentCount * $commentWeight);

        return $interactionScore;
    }

    /**
     * Relationships
     */
    public function categories()
    {
        return $this->belongsToMany(ArticleCategory::class, 'articles_article_categories')
            ->where('parent_id', null)
            ->withTimestamps();
    }

    // NOTE since this is a self-referencing relationship, sync will override categories!
    public function subCategories()
    {
        return $this->belongsToMany(ArticleCategory::class, 'articles_article_categories')
            ->where('parent_id', '!=', null)
            ->withTimestamps();
    }

    public function tags()
    {
        return $this->belongsToMany(ArticleTag::class, 'articles_article_tags')
            ->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function userFollowers()
    {
        return $this->hasManyThrough(User::class, UserFollowing::class, 'following_id', 'id', 'user_id', 'user_id');
    }

    public function userFollowings()
    {
        return $this->hasManyThrough(User::class, UserFollowing::class, 'user_id', 'id', 'user_id', 'following_id');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function interactions()
    {
        return $this->morphMany(Interaction::class, 'interactable');
    }

    public function reports()
    {
        return $this->morphMany(Reports::class, 'reportable');
    }

    public function likes()
    {
        return $this->morphMany(Interaction::class, 'interactable')
            ->where('type', Interaction::TYPE_LIKE);
    }

    // public function relatedThroughCategory()
    // {
    //     return $this->hasManyRelatedThrough(ArticleCategory::class, 'articles_article_categories');
    // }

    // public function relatedThroughViews()
    // {
    //     return $this->hasManyRelatedThrough(View::class, 'user_id');
    // }

    public function hiddenUsers()
    {
        return $this->belongsToMany(User::class, 'articles_hidden_users')
            ->withPivot('hide_until')
            ->withTimestamps();
    }

    public function imports()
    {
        return $this->belongsToMany(ArticleImport::class, 'articles_article_imports');
    }

    public function views()
    {
        return $this->morphMany(View::class, 'viewable');
    }

    public function location()
    {
        return $this->morphToMany(Location::class, 'locatable');
    }

    public function taggedUsers()
    {
        return $this->belongsToMany(User::class, 'articles_tagged_users', 'article_id', 'user_id')
            ->withTimestamps();
    }

    public function merchantOffers()
    {
        return $this->belongsToMany(MerchantOffer::class, 'articles_merchant_offers', 'article_id', 'merchant_offer_id')
            ->withTimestamps();
    }

    public function stores()
    {
        // stores is related to articles via locatables
        return $this->belongsToMany(Store::class, 'locatables', 'locatable_id', 'locatable_id')
            ->where('locatables.locatable_type', Store::class)
            ->wherePivotIn('location_id', function ($query) {
                $query->select('location_id')
                    ->from('locatables')
                    ->where('locatable_type', Article::class);
            })
            ->withTimestamps();
    }

    public function shareableLinks()
    {
        return $this->morphMany(ShareableLink::class, 'model');
    }

    public function searchKeywords()
    {
        return $this->belongsToMany(SearchKeyword::class, 'search_keywords_articles', 'article_id', 'search_keyword_id')
            ->withTimestamps();
    }

    /**
     * Scope a query to only include published articles.
     */
    public function scopePublished(Builder $query): void
    {
        $query->where($this->getTable() . '.status', self::STATUS_PUBLISHED);
    }

    public function scopePublic(Builder $query): void
    {
        $query->where($this->getTable() . '.visibility', self::VISIBILITY_PUBLIC);
    }

    public function scopeNotHiddenFromHome(Builder $query): void
    {
        $query->where($this->getTable() . '.hidden_from_home', false);
    }
}
