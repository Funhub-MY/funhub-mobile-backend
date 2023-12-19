<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoArchiveKeyword extends Model
{
    use HasFactory;

    protected $table = 'auto_archive_keywords';
    protected $guarded = ['id'];

}
