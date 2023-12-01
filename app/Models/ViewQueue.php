<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ViewQueue extends Model
{
    use HasFactory;

    protected $table = 'view_queues';

    protected $guarded = ['id'];

    // protected $fillable = [
    //     'article_id',
    //     'scheduled_views',
    //     'is_processed',
    //     'scheduled_at',
    // ];
}
