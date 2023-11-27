<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFollowing extends BaseModel
{
    use HasFactory;

    protected $table = 'users_followings';

    protected $fillable = [
        'user_id',
        'following_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function followingUser()
    {
        return $this->belongsTo(User::class, 'following_id');
    }
}
