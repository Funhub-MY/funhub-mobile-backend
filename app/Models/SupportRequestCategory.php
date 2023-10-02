<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportRequestCategory extends Model
{
    use HasFactory;

    protected $table = 'support_requests_categories';

    protected $guarded = ['id'];

    public function support_requests()
    {
        return $this->hasMany(SupportRequest::class, 'category_id');
    }

    public function scopePublished(Builder $query) : void
    {
        $query->where('status', 1);
    }
}
