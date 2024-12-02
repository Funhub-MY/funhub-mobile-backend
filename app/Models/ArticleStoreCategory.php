<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleStoreCategory extends Model
{
    use HasFactory;

	protected $fillable = [
		'article_category_id',
		'merchant_category_id',
	];

	public function articleCategory()
	{
		return $this->belongsTo(ArticleCategory::class);
	}

	public function merchantCategory()
	{
		return $this->belongsTo(MerchantCategory::class);
	}
}
