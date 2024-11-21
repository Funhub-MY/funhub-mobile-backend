<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray($request)
    {
        $originalUrl = $this->original_url;
        if ($originalUrl && strpos($originalUrl, 'cloudfront') !== false) {
            $parts = explode('/', $originalUrl);
            $parts[count($parts) - 1] = urlencode($parts[count($parts) - 1]);
            $originalUrl = implode('/', $parts);
        }

        $response = [
            'name' => $this->name,
            'file_name' => $this->file_name,
            'uuid' => $this->uuid,
            'original_url' => $originalUrl,
            'order' => $this->order,
            'custom_properties' => $this->custom_properties,
            'extension' => $this->extension,
            'size' => $this->size,
        ];

        // add video resolutions if this is a video with processed resolutions
        // video resolutions refer to VideoJob
        if (str_contains($this->mime_type, 'video') && $this->video_resolutions) {
            $response['resolutions'] = [
                'abr' => $this->video_resolutions['abr'] ?? null,
                'low' => $this->video_resolutions['low'] ?? null,
                'medium' => $this->video_resolutions['medium'] ?? null,
                'high' => $this->video_resolutions['high'] ?? null,
            ];
        }

        return $response;
    }
}
