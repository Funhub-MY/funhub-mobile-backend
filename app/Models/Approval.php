<?php

namespace App\Models;

use App\Services\PointService;
use App\Services\PointComponentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Approval extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function approvalSetting()
    {
        return $this->belongsTo(ApprovalSetting::class);
    }

    public function approvable()
    {
        return $this->morphTo();
    }
}