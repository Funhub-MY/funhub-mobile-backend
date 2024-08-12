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
        $myParticipation = $this->participants()->where('user_id', auth()->user()->id);

        if ($this->frequency == 'one-off') {
            $myParticipation = $myParticipation->latest()->first();
        } elseif ($this->frequency == 'daily') {
            $myParticipation = $myParticipation
                ->where('missions_users.created_at', '>=', now()->startOfDay())
                ->where('missions_users.created_at', '<', now()->endOfDay())
                ->latest()
                ->first();
        } elseif ($this->frequency == 'monthly') {
            $myParticipation = $myParticipation
                ->where('missions_users.created_at', '>=', now()->startOfMonth())
                ->where('missions_users.created_at', '<', now()->endOfMonth())
                ->latest()
                ->first();
        }

        $currentValues = [];
        if ($myParticipation) {
            $myParticipation = $myParticipation->pivot;
            $currentValues = json_decode($myParticipation->current_values, true);
        }

        // get localte from app or from http header
        $locale = config('app.locale');
        if ($request->header('X-Locale')) {
            $locale = $request->header('X-Locale');
        }

        $translatedName = $this->name;
        if (isset($this->name_translation)) {
            $translatedNames = json_decode($this->name_translation, true);
            if ($translatedNames && isset($translatedNames[$locale])) {
                $translatedName = $translatedNames[$locale];
            } else {
                $translatedName = $translatedNames[config('app.locale')];
            }
        }

        $translatedDescription = $this->description;
        if (isset($this->description_translation)) {
            $translatedDescriptions = json_decode($this->description_translation, true);
            if ($translatedDescriptions && isset($translatedDescriptions[$locale])) {
                $translatedDescription = $translatedDescriptions[$locale];
            } else {
                $translatedDescription = $translatedDescriptions[config('app.locale')];
            }
        }

        // current values {"like_article": 5}
        // get the first object value only
        $progress = 0;
        $goal = 0;
        if (is_array($currentValues) && count($currentValues) > 0) {
            $progress = array_values($currentValues)[0];
        }

        if (is_string($this->values)) {
            $goal = (int) $this->values;
        } else if (is_array($this->values) && count($this->values) > 0) {
            $goal = (int) array_values($this->values)[0];
        }

        return [
            'id' => $this->id, // mission id
            'name' => $translatedName, // mission name
            'image_url' => $this->getFirstMediaUrl(Mission::MEDIA_COLLECTION_NAME), // mission image
            'is_participating' => $isParticipating, // is user participating in this mission
            'description' => $translatedDescription, // mission description
            'events' => $this->events, // events that caused this mission
            'current_values' => $currentValues, // current values for each event
            'values' => $this->values, // target values for each event
            'reward' => $this->missionable, // reward or reward component
            'reward_quantity' => $this->reward_quantity, // quantity of reward
            'auto_disburse_rewards' => $this->auto_disburse_rewards, // auto disburse rewards
            'progress' => $progress, // progress of mission
            'goal' => $goal, // goal of mission
            'is_completed' => ($myParticipation) ? (bool) $myParticipation->is_completed : false,
            'completed_at' => ($myParticipation) ? $myParticipation->completed_at : null,
            'completed_at_formatted' => ($myParticipation && $myParticipation->completed_at) ? Carbon::parse($myParticipation->completed_at)->format('d/m/Y') : null,
            'last_rewarded_at' => ($myParticipation) ? $myParticipation->last_rewarded_at : null, // applicable for auto disburse rewards
            'claimed' => ($myParticipation) ? (bool) ($myParticipation->claimed_at) : false, // only applicable non-auto disburse rewards
            'claimed_at' => ($myParticipation) ? $myParticipation->claimed_at : null, // only applicable non-auto disburse rewards
            'claimed_at_formatted' => ($myParticipation && $myParticipation->claimed_at) ? Carbon::parse($myParticipation->completed_at)->format('d/m/Y') : null,  // only applicable non-auto disburse rewards
            'claimed_at_ago' => ($myParticipation && $myParticipation->claimed_at) ? Carbon::parse($myParticipation->completed_at)->diffForHumans() : null, // only applicable non-auto disburse rewards
        ];
    }
}
