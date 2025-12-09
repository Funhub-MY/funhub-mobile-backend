<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserMission;
use Carbon\Carbon;

class UserMissionsController extends Controller
{
    public function completeMission($missionId)
    {
        $userId = auth()->user()->id;

        if ($missionId < UserMission::MISSION_ONE || $missionId > UserMission::MISSION_FOUR) {
            return response()->json([
                'success' => false,
                'data' => [
                    'campaign_id' => 66,
                ],
                'message' => 'Invalid mission number.'
            ], 400);
        }

        $mission = UserMission::firstOrCreate(['user_id' => $userId]);
        $mission_column = 'mission_' . $missionId;

        // Check if mission already completed
        if ($mission->$mission_column == 1) {
            return response()->json([
                'success' => false,
                'data' => [
                    'campaign_id' => 66,
                ],
                'message' => 'Mission already completed.'
            ]);
        }

        // Mark mission as completed
        $mission->update([
            $mission_column => 1,
            'updated_at' => Carbon::now()
        ]);

        // Check if all missions are completed
        $allCompleted = true;
        for ($i = 1; $i <= 4; $i++) {
            if ($mission->{'mission_'.$i} == 0) {
                $allCompleted = false;
                break;
            }
        }

        if ($allCompleted) {
            $mission->where('cycle', 0)->update([
                'draw_chance' => 1,
                'cycle' => 1
            ]);
            
            $mission->refresh();

            return response()->json([
                'success' => true,
                'message' => "All missions completed! You have earned a lucky draw chance.",
                'data' => [
                    'campaign_id' => 66,
                    'mission_1' => $mission->mission_1 ?? 0,
                    'mission_2' => $mission->mission_2 ?? 0,
                    'mission_3_4' => ($mission->mission_3 ?? 0) + ($mission->mission_4 ?? 0),
                ]
            ]);
        }

        $mission->refresh();

        return response()->json([
            'success' => true,
            'message' => "Mission {$missionId} completed successfully!",
            'data' => [
                'user_id' => $userId,
                'campaign_id' => 66,
                'mission_1' => $mission->mission_1 ?? 0,
                'mission_2' => $mission->mission_2 ?? 0,
                'mission_3_4' => ($mission->mission_3 ?? 0) + ($mission->mission_4 ?? 0),
            ]
        ]);
    }

    public function getMissionProgress(){
        $userId = auth()->user()->id;

        $mission = UserMission::firstOrCreate(['user_id' => $userId]);

        $luckDrawChance = ($mission->draw_chance ?? 0) + ($mission->extra_chance ?? 0);

        return response()->json([
            'success' => true,
            'message' => 'Mission details fetched successfully.',
            'data' => [
                'user_id' => $userId,
                'campaign_id' => 66,
                'mission_1' => $mission->mission_1 ?? 0,
                'mission_2' => $mission->mission_2 ?? 0,
                'mission_3_4' => ($mission->mission_3 ?? 0) + ($mission->mission_4 ?? 0),
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
                'data' => [
                    'campaign_id' => 66,
                ],
                'message' => 'User Mission not found!',
            ]);
        }

        if($mission->draw_chance == 0 && $mission->extra_chance == 0){
            return response()->json([
                'success' => false,
                'data' => [
                    'campaign_id' => 66,
                ],
                'message' => 'Complete mission or puchase more fun card to get more lucky draw chance!',
            ]);
        }

        $luckDrawChance = ($mission->draw_chance ?? 0) + ($mission->extra_chance ?? 0);

        $mission->increment('total_drawn',$luckDrawChance);
        
        $mission->refresh();

        $mission->update([
            'draw_chance' => 0,
            'extra_chance' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lucky Draw redeemed succussfully',
            'data' => [
                'campaign_id' => 66,
                'user_id' => $userId,
                'total_drawn' =>  $mission->total_drawn ?? 0,
            ]
        ]);
    }
}
