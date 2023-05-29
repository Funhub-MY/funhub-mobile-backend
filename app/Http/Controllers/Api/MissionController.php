<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MissionResource;
use App\Models\Mission;
use Illuminate\Http\Request;

class MissionController extends Controller
{
    /**
     * Get all missions available.
     * 
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * 
     * @group Mission
     * @response scenario=success {
     * "data": [
     *    {
     *     "id": 1,
     *     "name": "Mission 1",
     *     "description": "Mission 1",
     *     "event": "Like Article",
     *     "reward": "Egg",
     *     "reward_quantity": 1
     *    }
     * ]
     * }
     */
    public function index()
    {
        // get all missions available
        $missions = Mission::enabled()  
            ->paginate(config('app.paginate_per_page'));

        return MissionResource::collection($missions);
    }
}
