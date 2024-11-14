<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SystemNotification extends Model implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    const REDIRECT_STATIC = 0;
    const REDIRECT_DYNAMIC = 1;
    const REDIRECT_PAGE = 2;

    const REDIRECT_TYPE = [
        self::REDIRECT_STATIC => 'Static',
        self::REDIRECT_DYNAMIC => 'Dynamic',
        self::REDIRECT_PAGE => 'Redirect',
    ];

    protected $guarded = ['id'];

    // public function user()
    // {
    //     return $this->belongsToMany(User::class, 'system_notification_user', 'user', 'id');
    // }

	public function users()
	{
		return $this->belongsToMany(User::class, 'system_notifications_users', 'system_notification_id', 'user_id')
			->withTimestamps();
	}

    public function content()
    {
        return $this->morphTo();
    }
}
