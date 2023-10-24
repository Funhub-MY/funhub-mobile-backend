<?php

namespace App\Models;

use Spatie\Permission\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApprovalSetting extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function approvals()
    {
        return $this->hasMany(Approval::class);
    }

    public function approvable()
    {
        return $this->morphTo();
    }

    public static function getSettingsForModel($approvableType)
    {
        return self::where('approvable_type', $approvableType)->orderBy('sequence')->get();
    }
}
