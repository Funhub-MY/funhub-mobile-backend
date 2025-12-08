<?php

namespace App\Http\Resources;

use App\Models\Reservation;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // Get form data - files are already stored in form_data with media info
        $formData = $this->form_data ?? [];
        
        // Update file URLs if they're stored as media_id only
        $formFiles = $this->getFormFiles();
        foreach ($formFiles as $media) {
            $fieldKey = $media->getCustomProperty('field_key');
            
            if ($fieldKey && isset($formData[$fieldKey])) {
                // If it's just a media_id, replace with full file info
                if (is_numeric($formData[$fieldKey]) || (is_array($formData[$fieldKey]) && isset($formData[$fieldKey]['media_id']))) {
                    $formData[$fieldKey] = [
                        'media_id' => $media->id,
                        'url' => $media->getUrl(),
                        'name' => $media->name,
                        'size' => $media->size,
                        'mime_type' => $media->mime_type,
                    ];
                } elseif (is_array($formData[$fieldKey])) {
                    // Update URL if media exists
                    $formData[$fieldKey]['url'] = $media->getUrl();
                }
            }
        }

        return [
            'id' => $this->id,
            'campaign' => [
                'id' => $this->campaign->id ?? null,
                'title' => $this->campaign->title ?? null,
            ],
            'reservation_date' => $this->reservation_date?->format('Y-m-d H:i:s'),
            'amount' => $this->amount,
            'status' => $this->status,
            'approval_status' => $this->approval_status,
            'approved_by' => $this->approvedBy ? [
                'id' => $this->approvedBy->id,
                'name' => $this->approvedBy->name,
            ] : null,
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'approval_notes' => $this->approval_notes,
            'rejection_reason' => $this->rejection_reason,
            'form_data' => $formData,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

