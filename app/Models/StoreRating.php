<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreRating extends BaseModel
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

    public function interactions()
    {
        return $this->morphMany(Interaction::class, 'interactable');
    }

    public function likes()
    {
        return $this->morphMany(Interaction::class, 'interactable')
        ->where('type', Interaction::TYPE_LIKE);
    }

    public function dislikes()
    {
        return $this->morphMany(Interaction::class, 'interactable')
        ->where('type', Interaction::TYPE_DISLIKE);
    }

    public function ratingCategories()
    {
        return $this->belongsToMany(RatingCategory::class, 'rating_categories_store_ratings')
                ->withPivot(['user_id'])
                ->withTimestamps();
    }
}
