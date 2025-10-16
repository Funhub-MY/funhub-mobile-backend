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

        if ($missionNumber < UserMission::MISSION_ONE || $missionNumber > UserMission::MISSION_THREE) {
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

        $allCompleted = true;
        for ($i = 1; $i <= UserMission::MISSION_THREE ; $i++) {
            if ($mission->{'mission_'.$i} == 0) {
                $allCompleted = false;
                break;
            }
        }

        if ($allCompleted) {
            $mission->where('cycle',0)->update([
                'draw_chance' => 1,
                'cycle' => 1
            ]);
            
            $mission->refresh();

            return response()->json([
                'success' => true,
                'message' => "All missions completed! You have earned a lucky draw chance.",
                'data' => $mission
            ]);
        }

        $mission->refresh();

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
        $luckDrawChance = 0;

        $mission = UserMission::firstOrCreate(['user_id' => $userId]);

        $progress = [];
        for ($i = 1; $i <= UserMission::MISSION_THREE; $i++) {
            $progress["mission_$i"] = $mission->{'mission_'.$i};
        }

        $luckDrawChance = ($mission->draw_chance ?? 0) + ($mission->extra_chance ?? 0);

        return response()->json([
            'success' => true,
            'message' => 'Mission details fetched successfully.',
            'data' => [
                'user_id' => $userId,
                'missions_progress' => $progress,
                'luck_draw_chance' => $luckDrawChance,
                'total_drawn' => $mission->total_drawn ?? 0,
            ]
        ]);
    }

    public function collectLuckDraw(){
        $userId = auth()->user()->id;
        $luckDrawChance = 0;

        $mission = UserMission::where('user_id',$userId)->first();

        if(!$mission){
            return response()->json([
                'success' => false,
                'message' => 'User Mission not found!',
            ]);
        }

        $luckDrawChance = ($mission->draw_chance ?? 0) + ($mission->extra_chance ?? 0);

        $mission->increment('total_draw',$luckDrawChance);
        $mission->refresh();

        $mission->update([
            'draw_chance' => 0,
            'extra_chance' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lucky Draw redeemed succussfully',
            'data' => [
                'user_id' => $userId,
                'lucky_draw_chance' => $luckDrawChance,
                'total_drawn' =>  $mission->total_draw,
            ]
        ]);
    }
}
