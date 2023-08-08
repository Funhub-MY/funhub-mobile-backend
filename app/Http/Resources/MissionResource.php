<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

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
        $isParticipating = $this->participants->contains(auth()->user()->id);   
        $myParticipation = $this->participants->first();

        if ($myParticipation) {
            $myParticipation = $myParticipation->pivot;
        }

        return [
            'id' => $this->id, // mission id
            'name' => $this->name, // mission name
            'is_participating' => $isParticipating, // is user participating in this mission
            'description' => $this->description, // mission description
            'event' => $this->event, // event that caused this mission
            'current_value' => ($myParticipation) ? $myParticipation->current_value : 0,
            'value' => $this->value, // value met to complete mission
            'reward' => $this->missionable, // reward or reward component
            'reward_quantity' => $this->reward_quantity, // quantity of reward
            'claimed' => ($myParticipation) ? (bool) $myParticipation->is_completed : false,
            'claimed_at' => ($myParticipation) ? $myParticipation->completed_at : null,
            'claimed_at_formatted' => ($myParticipation && $myParticipation->completed_at) ? Carbon::parse($myParticipation->completed_at)->format('d/m/Y') : null,
            'claimed_at_ago' =>($myParticipation && $myParticipation->completed_at) ? Carbon::parse($myParticipation->completed_at)->diffForHumans() : null,
        ];
    }
}
