<?php

namespace App\Models;

use App\Models\BaseModel;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportRequestCategory extends BaseModel implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;


    protected $table = 'support_requests_categories';

    protected $guarded = ['id'];

    const TYPES = [
        'complain',
        'bug',
        'feature_request',
        'others'
    ];

    public function support_requests()
    {
        return $this->hasMany(SupportRequest::class, 'category_id');
    }

    public function scopePublished(Builder $query) : void
    {
        $query->where('status', 1);
    }
}
