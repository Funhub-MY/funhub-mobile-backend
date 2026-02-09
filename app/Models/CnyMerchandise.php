<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CnyMerchandise extends Model
{
    use HasFactory;

    protected $table = 'cny_merchandise';

    protected $guarded = ['id'];

    protected $casts = [
        'win_percentage' => 'float',
    ];

    public function wins()
    {
        return $this->hasMany(CnyMerchandiseWin::class, 'cny_merchandise_id');
    }

    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->quantity - $this->given_out);
    }

    public function hasStock(): bool
    {
        return $this->given_out < $this->quantity;
    }
}
