<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignQuestionAnswerResource;
use App\Http\Resources\CampaignQuestionResource;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\CampaignQuestionAnswer;
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
            'has_active_campaign' => ($campaigns->count() > 0) ? true : false,
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
                'message' => __('messages.error.campaign_controller.Campaign_not_found'),
            ], 404);
        }

        $questions = CampaignQuestion::where('campaign_id', $request->campaign_id)
            ->where('is_active', true)
            ->get();

        // check if user has completed questions by brand
        $hasUserCompleted = [];
        // get brands unique from campaign questions
        $brands = $questions->pluck('brand')->unique();
        foreach ($brands as $brand) {
            $answered = CampaignQuestionAnswer::whereIn('campaign_question_id', $questions->pluck('id')->toArray())
                ->whereHas('question', function ($query) use ($brand) {
                    $query->where('brand', $brand);
                })
                ->where('user_id', auth()->user()->id)
                ->count();

            // total brand questions
            $total = $questions->where('brand', $brand)->count();

            // if match means user has completed
            if ($answered == $total) {
                $hasUserCompleted[$brand] = true;
            } else {
                $hasUserCompleted[$brand] = false;
            }
        }

        return response()->json([
            'campaign' => new CampaignResource($campaign),
            'questions' => CampaignQuestionResource::collection($questions),
            'questions_completed' => $hasUserCompleted,
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
                'message' => __('messages.error.campaign_controller.Campaign_not_found'),
            ], 404);
        }

        $questions = CampaignQuestion::where('campaign_id', $request->campaign_id)
            ->where('brand', $request->brand)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'campaign' => new CampaignResource($campaign),
            'questions' => CampaignQuestionResource::collection($questions),
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

        // deatch this quesiton id answer first
        $user->campaignAnswers()->detach($request->question_id);

        // attach with pivot answer
        $user->campaignAnswers()->attach($request->question_id, [
            'answer' => $request->answer,
        ]);

        return response()->json([
            'message' => __('messages.success.campaign_controller.Answer_saved_successfully'),
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
        if (!$campaign) {
            return response()->json([
                'message' => __('messages.error.campaign_controller.Campaign_not_found'),
            ], 404);
        }

        // get questions of this campaign
        $campaignQuestions = CampaignQuestion::where('campaign_id', $request->campaign_id)
            ->where('brand', $request->brand)
            ->orderBy('brand', 'asc')
            ->get();

        if (!$campaignQuestions) {
            return response()->json([
                'message' => __('messages.error.campaign_controller.Question(s)_not_found'),
            ], 404);
        }

        $answers = CampaignQuestionAnswer::whereIn('campaign_question_id', $campaignQuestions->pluck('id')->toArray())
            ->where('user_id', auth()->user()->id)
            ->get();

        // check if user has completed questions by brand
        $hasUserCompleted = [];
        // get brands unique from campaign questions
        $brands = $campaignQuestions->pluck('brand')->unique();
        foreach ($brands as $brand) {
            $answered = CampaignQuestionAnswer::whereIn('campaign_question_id', $campaignQuestions->pluck('id')->toArray())
                ->whereHas('question', function ($query) use ($brand) {
                    $query->where('brand', $brand);
                })
                ->where('user_id', auth()->user()->id)
                ->count();

            // total brand questions
            $total = $campaignQuestions->where('brand', $brand)->count();

            // if match means user has completed
            if ($answered == $total) {
                $hasUserCompleted[$brand] = true;
            } else {
                $hasUserCompleted[$brand] = false;
            }
        }

        return response()->json([
            'campaign' => new CampaignResource($campaign),
            'campaign_questions' => CampaignQuestionResource::collection($campaignQuestions),
            'questions_completed' => $hasUserCompleted,
            'answers' => CampaignQuestionAnswerResource::collection($answers),
        ]);
    }

    /**
     * Create Respondant Details
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group Campaigns
     * @bodyParam campaign_id integer required The ID of the campaign. Example: 1
     * @bodyParam name string required The name of the respondant. Example: John Doe
     * @bodyParam email string required The email of the respondant. Example:
     * @bodyParam phone string required The phone of the respondant. Example: 0123456789
     * @bodyParam ic string required The ic of the respondant. Example: 123456789012
     * @bodyParam address string required The address of the respondant. Example: 123, Jalan ABC, 12345, Kuala Lumpur
     *
     * @response scenario="success" {
     * "message": "Respondant details created successfully"
     * }
     */
    public function postCreateCampaignRespondantDetails(Request $request)
    {
        $this->validate($request, [
            'campaign_id' => 'required',
            'name' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'ic' => 'required',
        ]);

        $campaign = Campaign::find($request->campaign_id);
        if (!$campaign) {
            return response()->json([
                'message' => __('messages.error.campaign_controller.Campaign_not_found'),
            ], 404);
        }

        $campaign->respondantDetails()->create([
            'user_id' => auth()->user()->id,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'ic' => $request->ic,
            'address' => ($request->has('address')) ? $request->address : null,
        ]);

        return response()->json([
            'message' => __('messages.success.campaign_controller.Respondant_details_created_successfully')
        ]);
    }

    /**
     * Get Respondant Details of User
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group Campaigns
     * @bodyParam campaign_id integer required The ID of the campaign. Example: 1
     * @response scenario="success" {
     * "respondant_details": {},
     * "has_submitted_respondant_details": true
     * }
     */
    public function getRespondantDetails(Request $request)
    {
        $this->validate($request, [
            'campaign_id' => 'required',
        ]);

        $campaign = Campaign::find($request->campaign_id);
        if (!$campaign) {
            return response()->json([
                'message' => __('messages.error.campaign_controller.Campaign_not_found'),
            ], 404);
        }

        $respondantDetails = $campaign->respondantDetails()->where('user_id', auth()->user()->id)->first();

        return response()->json([
            'respondant_details' => $respondantDetails,
            'has_submitted_respondant_details' => ($respondantDetails) ? true : false,
        ]);
    }
}
