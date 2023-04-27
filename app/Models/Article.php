<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Laravel\Scout\Searchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;

class Article extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, Searchable;

    const MEDIA_COLLECTION_NAME = 'article_gallery';

    const STATUS = [
        0 => 'Draft',
        1 => 'Published',
        2 => 'Archived'
    ];

    const STATUS_DRAFT = 0;
    const STATUS_PUBLISHED = 1;
    const STATUS_ARCHIVED = 2;

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
        return 'articles_index';
    }

    public function toSearchableArray()
    {
        return [
            'id' => (int) $this->id,
            'title' => $this->title,
            'thumbnail' => $this->getFirstMediaUrl(self::MEDIA_COLLECTION_NAME),
            'type' => $this->type,
            'categories' => $this->categories->pluck('id', 'name'),
            'tags' => $this->tags->pluck('id', 'name'),
            'status' => $this->status,
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'gallery' => $this->getMedia(self::MEDIA_COLLECTION_NAME)->map(function ($media) {
                return $media->getUrl();
            }),
        ];
    }

    /**
     * Relationships
     */
    public function categories()
    {
        return $this->belongsToMany(ArticleCategory::class, 'articles_article_categories')
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

    /**
     * Scope a query to only include published articles.
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', self::STATUS_PUBLISHED);
    }
}
