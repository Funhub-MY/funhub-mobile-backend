<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreRating extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ratingCategories()
    {
        return $this->belongsToMany(RatingCategory::class, 'rating_categories_store_ratings')
                ->withPivot(['user_id'])
                ->withTimestamps();
    }
}
