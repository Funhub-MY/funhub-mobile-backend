<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShareableLink extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id'];

    public function model()
    {
        return $this->morphTo();
    }

    public function interactions()
    {
        return $this->belongsToMany(ShareableLinkInteraction::class, 'interactions_shareable_links', 'shareable_link_id', 'interaction_id');
    }
}
