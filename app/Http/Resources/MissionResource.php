<?php

namespace App\Http\Resources;

use App\Models\Mission;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class MissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $isParticipating = $this->participants->contains(auth()->user()->id);
        $myParticipation = $this->participants->first();

        $currentValues = [];
        if ($myParticipation) {
            $myParticipation = $myParticipation->pivot;
            $currentValues = json_decode($myParticipation->current_values, true);
        }

        return [
            'id' => $this->id, // mission id
            'name' => $this->name, // mission name
            'image_url' => $this->getFirstMediaUrl(Mission::MEDIA_COLLECTION_NAME), // mission image
            'is_participating' => $isParticipating, // is user participating in this mission
            'description' => $this->description, // mission description
            'events' => $this->events, // events that caused this mission
            'current_values' => $currentValues, // current values for each event
            'values' => $this->values, // target values for each event
            'reward' => $this->missionable, // reward or reward component
            'reward_quantity' => $this->reward_quantity, // quantity of reward
            'auto_disburse_rewards' => $this->auto_disburse_rewards, // auto disburse rewards
            'is_completed' => ($myParticipation) ? (bool) $myParticipation->is_completed : false,
            'claimed' => ($myParticipation) ? (bool) $myParticipation->is_completed : false,
            'claimed_at' => ($myParticipation) ? $myParticipation->completed_at : null,
            'claimed_at_formatted' => ($myParticipation && $myParticipation->completed_at) ? Carbon::parse($myParticipation->completed_at)->format('d/m/Y') : null,
            'claimed_at_ago' => ($myParticipation && $myParticipation->completed_at) ? Carbon::parse($myParticipation->completed_at)->diffForHumans() : null,
        ];
    }
}
