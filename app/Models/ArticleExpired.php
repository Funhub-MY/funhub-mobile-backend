<?php

namespace App\Models;

class ArticleExpired  extends BaseModel
{
	protected $table = 'articles_expired';

	protected $fillable = [
		'article_id',
		'processed_time',
		'is_expired'
	];

	public function article()
	{
		return $this->belongsTo(Article::class);
	}
}