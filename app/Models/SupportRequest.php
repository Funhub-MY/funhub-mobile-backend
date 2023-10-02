<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportRequest extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    const STATUS = [
        0 => 'Pending',
        1 => 'In Progress',
        2 => 'Pending Info',
        3 => 'Closed',
        4 => 'Reoepend',
        5 => 'Invalid'
    ];

    const STATUS_PENDING = 0, STATUS_IN_PROGRESS = 1, STATUS_PENDING_INFO = 2, STATUS_CLOSED = 3, STATUS_REOPENED = 4, STATUS_INVALID = 5;

    public function requestor()
    {
        return $this->belongsTo(User::class, 'requestor_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function category()
    {
        return $this->belongsTo(SupportRequestCategory::class, 'category_id');
    }

    public function associated()
    {
        return $this->morphTo('associated');
    }

    public function messages()
    {
        return $this->hasMany(SupportRequestMessage::class, 'support_request_id');
    }
}
