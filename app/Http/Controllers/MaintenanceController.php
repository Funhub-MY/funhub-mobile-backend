<?php

namespace App\Http\Controllers;

use App\Models\Maintenance;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function getMaintenanceInfo()
    {
        $maintenance = Maintenance::paginate(config('app.paginate_per_page'));

        return response()->json([
            'data' => $maintenance
        ]);
    }
}
