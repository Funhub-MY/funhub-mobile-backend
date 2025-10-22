<?php

namespace App\Http\Controllers\Api;

use App\Models\KdccTeams;
use App\Models\KdccVote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\KdccTeamResource;
use App\Http\Resources\KdccVoteResource;

class KdccController extends Controller
{
    /**
     * Vote for a team in a specific category
     */
    public function vote(Request $request)
    {
        $request->validate([
            'team_id' => 'required|exists:kdcc_teams,id',
            'category_id' => 'required|integer'
        ]);

        $userId = auth()->user()->id;
        $teamId = $request->team_id;
        $categoryId = $request->category_id;

        $team = KdccTeams::where('id', $teamId)
            ->where('category_id', $categoryId)
            ->first();

        if (!$team) {
            return response()->json([
                'success' => false,
                'message' => 'Team does not belong to this category'
            ], 400);
        }

        $existingVote = KdccVote::where('user_id', $userId)
            ->where('category_id', $categoryId)
            ->first();

        if ($existingVote) {
            return response()->json([
                'success' => false,
                'message' => 'You have already voted a team in this category'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $vote = KdccVote::create([
                'user_id' => $userId,
                'team_id' => $teamId,
                'category_id' => $categoryId
            ]);

            $team->increment('vote_count');
            $vote->load('team');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vote recorded successfully',
                'data' => new KdccVoteResource($vote)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to record vote'
            ], 500);
        }
    }

    /**
     * Get all teams with vote counts
     */
    public function getTeams(Request $request)
    {
        $categoryId = $request->query('category_id');

        $query = KdccTeams::query();

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $teams = $query->orderBy('category_id')
            ->orderBy('vote_count', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => KdccTeamResource::collection($teams)
        ]);
    }

    /**
     * Get user's voting history
     */
    public function getUserVotes()
    {
        $userId = auth()->user()->id;

        $votes = KdccVote::where('user_id', $userId)
            ->with('team')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => KdccVoteResource::collection($votes)
        ]);
    }

    public function showLeaderboard(Request $request)
    {
        $categoryId = $request->query('category');
        
        $query = KdccTeams::query();
        
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }
        
        $teams = $query->orderBy('vote_count', 'desc')
                    ->orderBy('name', 'asc')
                    ->get();
        
        // Get all teams for total count
        $allTeams = KdccTeams::all();
        $totalVotes = $allTeams->sum('vote_count');
        
        // Get unique categories
        $categories = KdccTeams::distinct()->pluck('category_id')->sort();
        
        return view('kdcc.leaderboard', compact('teams', 'allTeams', 'totalVotes', 'categories'));
    }
}