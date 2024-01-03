<?php

namespace App\Http\Controllers;

use App\Models\Maintenance;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function getMaintenanceInfo()
    {
        $maintenance = Maintenance::latest()->first();

        return response()->json([
            'start_date' => $maintenance->start_date,
            'end_date' => $maintenance->end_date,
            'is_active' => $maintenance->is_active,
        ]);
    }
}
