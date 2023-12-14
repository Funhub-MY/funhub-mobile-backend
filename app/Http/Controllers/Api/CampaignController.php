<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignQuestionResource;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    /**
     * Get Active Campaigns
     *
     * @return JsonResponse
     *
     * @group Campaigns
     * @response scenario="success" {
     * "has_active_campaign": true,
     * "campaigns": []
     * }
     */
    public function getActiveCampaigns()
    {
        $campaigns = Campaign::where('is_active', true)->get();

        return response()->json([
            'has_active_campaign' => $campaigns,
            'campaigns' => CampaignResource::collection($campaigns),
        ]);
    }

    /**
     * Get Questions by Campaign
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group Campaigns
     * @bodyParam campaign_id integer required The ID of the campaign. Example: 1
     * @response scenario="success" {
     * "campaign": {},
     * "questions": []
     * }
     */
    public function getQuestionsByCampaign(Request $request)
    {
        $this->validate($request, [
            'campaign_id' => 'required|exists:campaigns,id',
        ]);

        $campaign = Campaign::find($request->campaign_id);

        if (!$campaign) {
            return response()->json([
                'message' => 'Campaign not found',
            ], 404);
        }

        $questions = CampaignQuestion::where('campaign_id', $request->campaign_id)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'campaign' => new CampaignResource($campaign),
            'questions' => new CampaignQuestionResource($questions),
        ]);
    }

    /**
     * Get Questions by Campaign and Brand
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group Campaigns
     * @bodyParam campaign_id integer required The ID of the campaign. Example: 1
     * @bodyParam brand string required The brand of the campaign. Example: Brand A
     * @response scenario="success" {
     * "campaign": {},
     * "questions": []
     * }
     */
    public function getCampaignQuestionsByBrand(Request $request)
    {
        $this->validate($request, [
            'brand' => 'required',
        ]);

        $campaign = Campaign::find($request->campaign_id);

        if (!$campaign) {
            return response()->json([
                'message' => 'Campaign not found',
            ], 404);
        }

        $questions = CampaignQuestion::where('campaign_id', $request->campaign_id)
            ->where('brand', $request->brand)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'campaign' => new CampaignResource($campaign),
            'questions' => new CampaignQuestionResource($questions),
        ]);
    }

    /**
     * Save Single Answer to a Question
     *
     * @param Request $request
     * @return void
     *
     * @group Campaigns
     * @bodyParam question_id integer required The ID of the question. Example: 1
     * @bodyParam answer string required The answer to the question. Example: Yes/A/B/C/D/Text
     * @response scenario="success" {
     * "message": "Answer saved successfully"
     * }
     */
    public function postSingleAnswer(Request $request)
    {
        $this->validate($request, [
            'question_id' => 'required|exists:campaigns_questions,id',
            'answer' => 'required',
        ]);
        $user = auth()->user();

        $user->campaignAnswers()->syncWithoutDetaching($request->question_id, [
            'answer' => $request->answer,
        ]);

        return response()->json([
            'message' => 'Answer saved successfully',
        ]);
    }

    /**
     * Get My Answers by Campaign and Brand
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group Campaigns
     * @bodyParam campaign_id integer required The ID of the campaign. Example: 1
     * @bodyParam brand string required The brand of the campaign. Example: Brand A
     * @response scenario="success" {
     * "campaign": {},
     * "answers": []
     * }
     */
    public function getMyAnswersByCampaignAndBrand(Request $request)
    {
        $this->validate($request, [
            'campaign_id' => 'required|exists:campaigns,id',
            'brand' => 'required',
        ]);

        $campaign = Campaign::find($request->campaign_id);

        $answers = CampaignQuestion::where('campaign_id', $request->campaign_id)
            ->where('brand', $request->brand)
            ->where('is_active', true)
            ->with(['usersAnswers' => function ($query) {
                $query->where('user_id', auth()->user()->id);
            }])
            ->get();

        return response()->json([
            'campaign' => new CampaignResource($campaign),
            'answers' => $answers,
        ]);
    }
}
