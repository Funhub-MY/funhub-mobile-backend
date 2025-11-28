<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDrawChanceLog extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    const SOURCE_PRODUCT_PURCHASE = 'product_purchase';
    const SOURCE_ADMIN_MANUAL = 'admin_manual';
    const SOURCE_PROMO = 'promo';

    const SOURCES = [
        self::SOURCE_PRODUCT_PURCHASE => 'Product Purchase',
        self::SOURCE_ADMIN_MANUAL => 'Admin Manual',
        self::SOURCE_PROMO => 'Promotion',
    ];

    /**
     * User who received the chances
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Product that triggered the award
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Associated transaction
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}

