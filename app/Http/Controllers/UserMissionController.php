<?php

namespace App\Http\Controllers;

use App\Models\Mission;
use App\Models\UserMission;
use Illuminate\Http\Request;

class UserMissionController extends Controller
{
    public function today(Request $request)
    {       
        $user = $request->user();

        $missions = UserMission::getTodayMissions($user->id);

        if ($missions->isEmpty()) {

            $randomMissions = Mission::inRandomOrder()->limit(3)->get();

            foreach ($randomMissions as $mission) {
                UserMission::firstOrCreate([
                    'user_id' => $user->id,
                    'mission_id' => $mission->id,
                    'assigned_date' => today()
                ]);        
            }

            $missions = UserMission::getTodayMissions($user->id);
        }

        return response()->json([
            'data' => $missions
        ], 200);
    }

    public function allMissions(Request $request)
    {
        $user = $request->user();

        $missions = UserMission::getAllMissionsByUser($user->id);

        return response()->json([
            'data' => $missions
        ], 200);
    }
}
