<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantRating extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id'];

    const FILTERABLE = [
        'id',
        'merchant_id',
        'user_id',
        'rating',
        'comment',
        'created_at',
        'updated_at'
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ratingCategories()
    {
        return $this->belongsToMany(RatingCategory::class, 'rating_categories_merchant_ratings')
                ->withPivot(['user_id'])
                ->withTimestamps();
    }
}
