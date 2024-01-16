<?php
namespace App\Traits;

use Carbon\Carbon;

trait HasPeriodTrait {
    public function getPeriods($selectedPeriod) {
        $periods = [];
        if (!$selectedPeriod) {
            // get list of months from 12 months ago
            $periods = collect(range(11, 0))->map(function ($month) {
                return Carbon::now()->subMonths($month)->format('M Y');
            });
        } else {
            if ($selectedPeriod == 7) { // 7 days should show every day in the week
               // get list of days from 7 days ago
               $periods = collect(range(6, 0))->map(function ($day) {
                    if ($day == 0) {
                        return Carbon::now()->format('d/m/Y');
                    }
                    return Carbon::now()->subDays($day)->format('d/m/Y');
                });
            } else if ($selectedPeriod == 1) {
                // 1 month should show every day in the month
                $periods = collect(range(30, 0))->map(function ($day) {
                    if ($day == 0) {
                        return Carbon::now()->format('d/m/Y');
                    }
                    return Carbon::now()->subDays($day)->format('d/m/Y');
                });
            } else {
                 // get list of months from 12 months ago
                 $periods = collect(range($selectedPeriod - 1, 0))->map(function ($month) {
                    return Carbon::now()->subMonths($month)->format('M Y');
                });
            }
        }


        return $periods;
    }
}
