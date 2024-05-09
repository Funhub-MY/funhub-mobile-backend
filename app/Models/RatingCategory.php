<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RatingCategory extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id'];

    public function merchantRatings()
    {
        return $this->belongsToMany(MerchantRating::class, 'rating_categories_merchant_ratings')
                ->withTimestamps();
    }

    public function storeRatings()
    {
        return $this->belongsToMany(StoreRating::class, 'rating_categories_store_ratings')
                ->withPivot(['user_id'])
                ->withTimestamps();
    }
}
