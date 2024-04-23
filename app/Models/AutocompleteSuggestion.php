<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class AutocompleteSuggestion extends Model
{
    use Searchable;

    protected $guarded = [];

    public function searchableAs(): string
    {
        return config('scout.prefix').'search_keywords_index';
    }

    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'suggestion' => $this->suggestion,
            'city_name' => $this->city_name,
            'city_standardised_name' => $this->city_standardised_name,
            'city_id' => $this->city_id,
            'keyword' => $this->keyword,
            'keyword_id' => $this->keyword_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
