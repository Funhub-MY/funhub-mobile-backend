<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function categories()
    {
        return $this->belongsToMany(ArticleCategory::class)
            ->withTimestamps();
    }

    public function tags()
    {
        return $this->belongsToMany(ArticleTag::class)
            ->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
