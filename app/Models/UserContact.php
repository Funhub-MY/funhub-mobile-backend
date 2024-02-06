<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserContact extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $appends = ['full_phone_no'];

    public function importedByUser()
    {
        return $this->belongsTo(User::class, 'imported_by_id');
    }

    public function relatedUser()
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }

    /**
     * Get the user's full phone number
     *
     * @return string
     */
    public function getFullPhoneNoAttribute()
    {
        // response 60123456789
        return $this->phone_country_code . $this->phone_no;
    }
}
