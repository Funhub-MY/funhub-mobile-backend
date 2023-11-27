<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleTag extends BaseModel
{
    use HasFactory;
    protected $guarded = ['id'];

    // filterables
    const FILTERABLE = [
        'id',
        'name',
        'created_at',
        'updated_at'
    ];

    public function articles()
    {
        return $this->belongsToMany(Article::class, 'articles_article_tags');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
