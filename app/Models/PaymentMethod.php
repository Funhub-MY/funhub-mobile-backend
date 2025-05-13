<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

	protected $guarded = ['id'];

    public function promotionCodeGroups()
    {
        return $this->belongsToMany(PromotionCodeGroup::class, 'payment_method_promo_group')
            ->withTimestamps();
    }
}
