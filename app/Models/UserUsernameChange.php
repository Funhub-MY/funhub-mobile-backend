<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserUsernameChange extends Model
{
    use HasFactory;

    protected $table = 'user_username_changes';

    protected $fillable = [
        'user_id',
        'old_username',
        'new_username'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
