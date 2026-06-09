<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;

class MediaProxyController extends Controller
{
    public function __invoke(Media $media, ?string $conversion = null)
    {
        $disk = storage_resolve_disk($media->disk);
        $path = ltrim($conversion ? $media->getPath($conversion) : $media->getPath(), '/');

        abort_unless(Storage::disk($disk)->exists($path), 404);

        $headers = [];

        if ($media->mime_type) {
            $headers['Content-Type'] = $media->mime_type;
        }

        $headers['Cache-Control'] = 'private, max-age=3600';

        return Storage::disk($disk)->response($path, $media->file_name, $headers);
    }
}
