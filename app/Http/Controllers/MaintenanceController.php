<?php

namespace App\Http\Controllers;

use App\Http\Resources\MaintenanceResource;
use App\Models\Maintenance;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    /**
     * Get maintenance info
     *
     * @return void
     *
     * @group Maintenance
     * @authenticated
     * @response scenario=success {
     *  "data": [],
     * }
     *
     */
    public function getMaintenanceInfo()
    {
        $maintenance = Maintenance::orderBy('created_at', 'desc')
            ->paginate(config('app.paginate_per_page'));

        // is there active maintenance
        $hasActiveMaintenance = Maintenance::where('is_active', true)->exists();

        return response()->json([
            'is_active' => $hasActiveMaintenance,
            'schedules' => MaintenanceResource::collection($maintenance),
        ]);
    }
}
