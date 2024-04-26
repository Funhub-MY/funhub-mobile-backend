<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class SearchKeyword extends BaseModel
{
    use HasFactory, Searchable;

    protected $guarded = ['id'];
      /**
     * Search Setup
     */
    public function searchableAs(): string
    {
        return config('scout.prefix').'search_keywords_index';
    }

    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'keyword' => $this->keyword,
            'hits' => $this->hits,
            'blacklisted' => $this->blacklisted,
            'sponsored_from' => $this->sponsored_from,
            'sponsored_to' => $this->sponsored_to,
            'total_articles' => $this->articles->count(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    public function shouldBeSearchable(): bool
    {
        // only if not blacklisted
        return !$this->blacklisted;
    }


    public function scopeBlacklisted($query)
    {
        return $query->where('blacklisted', true);
    }

    public function locations()
    {
        return $this->hasManyThrough(Location::class, Article::class, 'search_keyword_id', 'id', 'id', 'id')
            ->join('articles_locations', 'articles_locations.location_id', '=', 'locations.id')
            ->join('search_keywords_articles', 'search_keywords_articles.article_id', '=', 'articles_locations.article_id');
    }

    public function articles()
    {
        return $this->belongsToMany(Article::class, 'search_keywords_articles', 'search_keyword_id', 'article_id')
            ->withTimestamps();
    }
}
