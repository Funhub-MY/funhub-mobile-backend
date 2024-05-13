<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ArticleEngagement extends BaseModel implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function users()
    {
        // belongs to many users
        return $this->belongsToMany(User::class, 'article_engagements_users', 'article_engagement_id', 'user_id')
            ->withTimestamps()
            ->where('for_engagement', true)
            ->where('status', User::STATUS_ACTIVE);
    }
}
