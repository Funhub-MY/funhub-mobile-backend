<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SystemNotification extends Model implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsToMany(User::class, 'system_notification_user', 'user', 'id');
    }
}
