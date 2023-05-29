<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MissionResource extends JsonResource
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
            'name' => $this->name, // mission name
            'description' => $this->description, // mission description
            'event' => $this->event, // event that caused this mission
            'value' => $this->value, // value met to complete mission
            'reward' => $this->missionable, // reward or reward component
            'reward_quantity' => $this->reward_quantity, // quantity of reward
        ];
    }
}
