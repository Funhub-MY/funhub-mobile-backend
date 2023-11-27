<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleImport extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id'
    ];
    protected $casts = [
        'description' => 'array'
    ];
    const STATUS = [
        0 => 'Failed',
        1 => 'Success',
    ];
    public $timestamps = false;
    const IMPORT_STATUS_FAILED = 0;
    const IMPORT_STATUS_SUCCESS = 1;

    public function rss_channel()
    {
        return $this->belongsTo(RssChannel::class);
    }

    public function articles()
    {
        return $this->belongsToMany(Article::class, 'articles_article_imports');
    }
}
