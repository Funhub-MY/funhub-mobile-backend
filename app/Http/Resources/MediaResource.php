<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // encode last part of original_url
        $originalUrl = $this->original_url;
        if ($originalUrl) {
            $parts = explode('/', $originalUrl);
            $parts[count($parts) - 1] = urlencode($parts[count($parts) - 1]);
            $originalUrl = implode('/', $parts);
        }

        return [
            'name' => $this->name,
            'file_name' => $this->file_name,
            'uuid' => $this->uuid,
            'original_url' => $originalUrl,
            'order' => $this->order,
            'custom_properties' => $this->custom_properties,
            'extension' => $this->extension,
            'size' => $this->size,
        ];
    }
}
