<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use App\Models\Merchant;
use Illuminate\Http\Resources\Json\JsonResource;

class SyncStoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'merchant_id' => $this->merchant_id,
            'name' => $this->name,
            'manager_name' => $this->manager_name,
            'business_phone_no' => $this->business_phone_no,
            'business_hours' => (object) $this->formatHours($this->business_hours),
            'rest_hours' => (object) $this->formatHours($this->rest_hours),
            'address' => $this->address,
            'address_postcode' => $this->address_postcode,
            'long' => $this->long,
            'lang' => $this->lang,
            'is_hq' => $this->is_hq,
            'is_closed' => $this->is_closed,
            'status' => $this->status,
            'state' => $this->state,
            'country' => $this->country,
            'state_id' => $this->state_id,
            'country_id' => $this->country_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'categories' => $this->categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name
                ];
            })
        ];
    }


    /**
     * Format hours into an object-like structure.
     *
     * @param string|null $hours
     * @return array|null
     */
    protected function formatHours($hours)
    {
        if (!$hours) {
            return null;
        }

        $decoded = json_decode($hours, true);

        // Ensure the keys are retained
        return collect($decoded)->mapWithKeys(function ($hour, $day) {
            return [
                (string) $day => [
                    'open_time' => $hour['open_time'] ?? null,
                    'close_time' => $hour['close_time'] ?? null,
                ]
            ];
        })->toArray();
    }
}
