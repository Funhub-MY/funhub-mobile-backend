<?php

namespace App\Http\Resources;

use Carbon\Carbon;
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
            'id' => $this->id, // mission id
            'name' => $this->name, // mission name
            'description' => $this->description, // mission description
            'event' => $this->event, // event that caused this mission
            'current_value' => ($this->pivot) ? $this->pivot->current_value : 0,
            'value' => $this->value, // value met to complete mission
            'reward' => $this->missionable, // reward or reward component
            'reward_quantity' => $this->reward_quantity, // quantity of reward
            'completed' => ($this->pivot) ? $this->pivot->is_completed : false,
            'completed_at' => ($this->pivot) ? $this->pivot->completed_at : null,
            'completed_at_formatted' => ($this->pivot && $this->pivot->completed_at) ? Carbon::parse($this->pivot->completed_at)->format('d/m/Y') : null,
            'copmpleted_at_ago' =>($this->pivot && $this->pivot->completed_at) ? Carbon::parse($this->pivot->completed_at)->diffForHumans() : null,
        ];
    }
}
