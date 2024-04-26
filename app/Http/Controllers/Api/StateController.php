<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StateResource;
use App\Models\State;
use Illuminate\Http\Request;

class StateController extends Controller
{
    /**
     * Get States
     * 
     * @return \Illuminate\Http\JsonResponse
     * 
     * @group Other
     * @subgroup State
     * @response scenario="success" {
     * ["id" => 1, "name" => "Abia", "code" => "AB", "country_id" => 1],
     * }
     * 
     */
    public function getStates(Request $request)
    {
        // Get the language from the request header, use the default language if not set
        $locale = $request->header('X-Locale') ?? config('app.locale');

        // Filter out states based on hardcoded list
        $states = [
            'Perlis', 'Kedah', 'Pulau Pinang', 'Perak', 'Pahang', 'Kelantan',
            'Terengganu', 'Selangor', 'W.P. Kuala Lumpur', 'W.P. Putrajaya', 'Negeri Sembilan',
            'Malacca', 'Johor', 'Sabah', 'Sarawak', 'W.P. Labuan', 'Others'
        ];

        $states = State::whereIn('name', $states)
            ->orderBy('name', 'ASC')
            ->get()
            ->map(function ($state) use ($locale) {
                $state->name_translation = json_decode($state->name_translation, true)[$locale] ?? $state->name;
                return $state;
            });
            
        return response()->json(StateResource::collection($states));
    }
}
