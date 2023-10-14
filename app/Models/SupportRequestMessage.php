<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class SupportRequestMessage extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $table = 'support_requests_messages';

    const MEDIA_COLLECTION_NAME = 'support_uploads';

    protected $guarded = ['id'];

    public function request()
    {
        return $this->belongsTo(SupportRequest::class, 'support_request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
