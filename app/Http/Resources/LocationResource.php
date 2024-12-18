<?php

namespace App\Http\Resources;

use App\Models\Location;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => (int)$this->id,
            'name' => $this->name,
            'lat' => (float)$this->lat,
            'lng' => (float)$this->lng,
            'address' => $this->address,
            'address_2' => $this->address_2,
            'postcode' => $this->zip_code,
            'city' => $this->city,
            'rated_count' => $this->ratings->count() ?? 0,
            'state' => [
                'id' => $this->state->id,
                'name' => $this->state->name
            ],
            'country' => [
                'id' => $this->country->id,
                'name' => $this->country->name
            ],
            'phone_no' => $this->phone_no,
            'average_ratings' => $this->average_ratings,
            'ratings' => ($this->ratings) ? LocationRatingResource::collection($this->ratings) : [],
            'cover' => $this->getFirstMediaUrl(Location::MEDIA_COLLECTION_NAME),
			'google_id' => $this->google_id
        ];
    }
}
