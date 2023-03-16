<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Article extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    const STATUS = [
        0 => 'Draft',
        1 => 'Published',
    ];

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
        return $this->morphMany(Report::class, 'reportable');
    }

    public function hiddenUsers()
    {
        return $this->belongsToMany(User::class, 'articles_hidden_users')
            ->withPivot('hide_until')
            ->withTimestamps();
    }

    /**
     * Scope a query to only include published articles.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePublished()
    {
        return $this->where('status', 1);
    }
}
