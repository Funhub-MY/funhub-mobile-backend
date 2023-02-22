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
        'text', 'video', 'audio', 'image'
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

}
