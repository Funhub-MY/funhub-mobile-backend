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
    public function getStates()
    {
        $states = State::orderBy('name', 'ASC')
            ->get();
            
        return response()->json(StateResource::collection($states));
    }
}
