<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Application extends Model
{
    use HasFactory, HasApiTokens;

    protected $fillable = [
        'name',
        'description',
        'status',
        'api_key',
        'webhook_url',
        'settings'
    ];

    protected $casts = [
        'settings' => 'json',
        'status' => 'boolean'
    ];

    const STATUS_ACTIVE = true;
    const STATUS_INACTIVE = false;

    public function tokens()
    {
        return $this->morphMany(\Laravel\Sanctum\PersonalAccessToken::class, 'tokenable');
    }
}
