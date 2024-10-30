<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KocMerchantHistory extends Model
{
	use HasFactory;

	protected $table = 'koc_merchant_history';

	protected $guarded = ['id'];

	public function merchant()
	{
		return $this->belongsTo(Merchant::class);
	}

	public function kocUser()
	{
		return $this->belongsTo(User::class, 'koc_user_id');
	}
}