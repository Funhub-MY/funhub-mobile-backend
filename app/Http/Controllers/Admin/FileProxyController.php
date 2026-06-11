<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileProxyController extends Controller
{
    public function __invoke(Request $request)
    {
        $disk = $request->query('disk');
        $path = $request->query('path');

        abort_unless(is_string($disk) && $disk !== '', 404);
        abort_unless(is_string($path) && $path !== '', 404);

        $disk = storage_resolve_disk($disk);
        $path = ltrim($path, '/');

        abort_unless(Storage::disk($disk)->exists($path), 404);

        $headers = ['Cache-Control' => 'private, max-age=3600'];

        $mimeType = Storage::disk($disk)->mimeType($path);

        if ($mimeType) {
            $headers['Content-Type'] = $mimeType;
        }

        return Storage::disk($disk)->response($path, basename($path), $headers);
    }
}
