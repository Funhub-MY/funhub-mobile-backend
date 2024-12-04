<?php

namespace App\Http\Resources;

use App\Models\Mission;
use App\Services\MissionService;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class MissionResource extends JsonResource
{
    public function toArray($request)
    {
        $user = auth()->user();
        $isParticipating = $this->participants->contains($user->id);

        // get relevant participation based on frequency
        $myParticipation = $this->participants()
            ->where('user_id', $user->id)
            ->when($this->frequency === 'daily', function($query) {
                return $query->where('started_at', '>=', now()->startOfDay())
                    ->orderByDesc('missions_users.id');
            }, function($query) {
                // for non-daily missions, get latest record
                return $query->orderByDesc('missions_users.id');
            })
            ->orderByDesc('missions_users.id')
            ->first();

        // get translated content
        $locale = $request->header('X-Locale', config('app.locale'));
        $translations = $this->getTranslations($locale);

        // calculate progress
        $progressData = $this->calculateProgress($myParticipation);

        return [
            'id' => $this->id,
            'name' => $translations['name'],
            'image_url' => $this->getFirstMediaUrl(Mission::MEDIA_COLLECTION_NAME),
            'completed_image_en_url' => $this->getFirstMediaUrl(Mission::COMPLETED_MISSION_COLLECTION_EN),
            'completed_image_zh_url' => $this->getFirstMediaUrl(Mission::COMPLETED_MISSION_COLLECTION_ZH),
            'is_participating' => $isParticipating,
            'description' => $translations['description'],
            'events' => $this->events,
            'current_values' => $progressData['currentValues'],
            'values' => $this->values,
            'reward' => $this->missionable,
            'reward_quantity' => $this->reward_quantity,
            'auto_disburse_rewards' => $this->auto_disburse_rewards,
            'progress' => $progressData['progress'],
            'goal' => $progressData['goal'],
            'is_completed' => $myParticipation ? (bool) $myParticipation->pivot->is_completed : false,
            'completed_at' => $myParticipation?->pivot->completed_at,
            'completed_at_formatted' => $myParticipation?->pivot->completed_at
                ? Carbon::parse($myParticipation->pivot->completed_at)->format('d/m/Y')
                : null,
            'last_rewarded_at' => $myParticipation?->pivot->last_rewarded_at,
            'claimed' => $myParticipation ? (bool) $myParticipation->pivot->claimed_at : false,
            'claimed_at' => $myParticipation?->pivot->claimed_at,
            'claimed_at_formatted' => $myParticipation?->pivot->claimed_at
                ? Carbon::parse($myParticipation->pivot->claimed_at)->format('d/m/Y')
                : null,
            'claimed_at_ago' => $myParticipation?->pivot->claimed_at
                ? Carbon::parse($myParticipation->pivot->claimed_at)->diffForHumans()
                : null,
            // 'pivot' => $this->whenPivotLoaded('missions_users', function () {
            //     return [
            //         'id' => $this->pivot->id,
            //         'is_completed' => (bool) $this->pivot->is_completed,
            //         'claimed_at' => $this->pivot->claimed_at,
            //         'last_rewarded_at' => $this->pivot->last_rewarded_at,
            //         'started_at' => $this->pivot->started_at,
            //         'current_values' => json_decode($this->pivot->current_values ?? '[]'),
            //         'completed_at' => $this->pivot->completed_at,
            //     ];
            // }),
            'predecessors' => MissionResource::collection($this->whenLoaded('predecessors')),
        ];
    }

    protected function getTranslations(string $locale): array
    {
        $defaultLocale = config('app.locale');

        $nameTranslations = json_decode($this->name_translation ?? '{}', true);
        $descTranslations = json_decode($this->description_translation ?? '{}', true);

        return [
            'name' => $nameTranslations[$locale] ?? $nameTranslations[$defaultLocale] ?? $this->name,
            'description' => $descTranslations[$locale] ?? $descTranslations[$defaultLocale] ?? $this->description,
        ];
    }

    protected function calculateProgress($participation): array
    {
        $values = is_string($this->values) ? json_decode($this->values, true) : $this->values;
        $goal = (int) (is_array($values) ? (array_values($values)[0] ?? 0) : $values);

        if (!$participation) {
            return [
                'currentValues' => [],
                'progress' => 0,
                'goal' => $goal
            ];
        }

        $currentValues = json_decode($participation->pivot->current_values, true) ?? [];
        $event = is_array($this->events) ? $this->events[0] : json_decode($this->events, true)[0];

        return [
            'currentValues' => $currentValues,
            'progress' => (int) ($currentValues[$event] ?? 0),
            'goal' => $goal
        ];
    }
}
