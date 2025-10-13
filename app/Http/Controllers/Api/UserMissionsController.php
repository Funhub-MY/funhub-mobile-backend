<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use Illuminate\Http\Request;
use App\Models\UserMission;
use Carbon\Carbon;

class UserMissionsController extends Controller
{
    public function completeMission($missionNumber)
    {
        $userId = auth()->user()->id;

        if ($missionNumber < UserMission::MISSION_ONE || $missionNumber > UserMission::MISSION_SIX) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid mission number.'
            ], 400);
        }

        $mission = UserMission::firstOrCreate(['user_id' => $userId]);
        $column = 'mission_' . $missionNumber;

        if ($mission->$column == 1) {
            return response()->json([
                'success' => false,
                'message' => 'Mission already completed.'
            ]);
        }

        $mission->update([
            $column => 1,
            'updated_at' => Carbon::now()
        ]);

        return response()->json([
            'success' => true,
            'message' => "Mission {$missionNumber} completed successfully!",
            'data' => $mission
        ]);
    }

    public function mission1() {
        return $this->completeMission(UserMission::MISSION_ONE); 
    }

    public function mission2() { 
        return $this->completeMission(UserMission::MISSION_TWO); 
    }
    public function mission3() { 
        return $this->completeMission(UserMission::MISSION_THREE); 
    }
    public function mission4() { 
        return $this->completeMission(UserMission::MISSION_FOUR); 
    }
    public function mission5() { 
        return $this->completeMission(UserMission::MISSION_FIVE); 
    }
    public function mission6() { 
        return $this->completeMission(UserMission::MISSION_SIX); 
    }

    public function getMissionProgress(){
        $userId = auth()->user()->id;

        $mission = UserMission::firstOrCreate(['user_id' => $userId]);

        $progress = [];
        for ($i = 1; $i <= UserMission::MISSION_SIX; $i++) {
            $progress["mission_$i"] = $mission->{'mission_'.$i};
        }

        return response()->json([
            'success' => true,
            'message' => 'Mission progress fetched successfully.',
            'data' => [
                'user_id' => $userId,
                'missions_progress' => $progress,
                'mission_1' => $mission->mission_1,
                'mission_2' => $mission->mission_2,
                'mission_3' => $mission->mission_3,
                'mission_4' => $mission->mission_4,
                'mission_5' => $mission->mission_5,
                'mission_6' => $mission->mission_6,
            ]
        ]);
    }
}
