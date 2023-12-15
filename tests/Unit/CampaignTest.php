<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\CampaignQuestion;
use Tests\TestCase;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CampaignTest extends TestCase
{
    use RefreshDatabase;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->refreshDatabase();

        // mock log in user get token
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user,['*']);
    }

    public function testGetActiveCampaigns()
    {
        // create one active, one inactive campaign
        $activeCampaign = Campaign::factory()->create([
            'is_active' => true
        ]);

        $inactiveCampaign = Campaign::factory()->create([
            'is_active' => false
        ]);

        // get active campaigns
        $response = $this->getJson('/api/v1/campaigns/active');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'has_active_campaign',
                'campaigns'
            ]);

        // count json campaigns is one
        $this->assertCount(1, $response->json('campaigns'));
    }

    public function testGetQuestionsByCampaign()
    {
        // create campaign first
        $campaign = Campaign::factory()->create([
            'is_active' => true
        ]);

        // create questions
        $questions = [
            [
                'campaign_id' => $campaign->id,
                'brand' => 'brand1',
                'question' => 'question1',
                'answer_type' => 'text',
                'answer' => 'answer1',
                'is_active' => true
            ],
            [
                'campaign_id' => $campaign->id,
                'brand' => 'brand1',
                'question' => 'question2',
                'answer' => 'answer2',
                'answer_type' => 'text',
                'is_active' => true
            ],
            [
                'campaign_id' => $campaign->id,
                'brand' => 'brand1',
                'question' => 'question3',
                'answer' => 'answer3',
                'answer_type' => 'text',
                'is_active' => true
            ],
        ];

        CampaignQuestion::insert($questions);

        // get questions by campaign
        $response = $this->getJson('/api/v1/campaigns/questions_by_campaign?campaign_id='. $campaign->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'campaign',
                'questions'
            ]);

        // valdiate each questions is correct with $questions
        $response->assertJsonCount(3, 'questions');
        collect($response->json('questions'))->each(function ($question, $key) use ($questions) {
            $this->assertEquals($question['campaign_id'], $questions[$key]['campaign_id']);
            $this->assertEquals($question['brand'], $questions[$key]['brand']);
            $this->assertEquals($question['question'], $questions[$key]['question']);
            $this->assertEquals($question['answer'], $questions[$key]['answer']);
            $this->assertEquals($question['type'], $questions[$key]['answer_type']);
        });
    }

    public function testSaveSingleAnswerByLoggedInUser()
    {
        // create campaign and three questions
        $campaign = Campaign::factory()->create([
            'is_active' => true
        ]);

        $questions = [
            [
                'campaign_id' => $campaign->id,
                'brand' => 'brand1',
                'question' => 'question1',
                'answer_type' => 'text',
                'answer' => 'answer1',
                'is_active' => true
            ],
            [
                'campaign_id' => $campaign->id,
                'brand' => 'brand1',
                'question' => 'question2',
                'answer' => 'answer2',
                'answer_type' => 'text',
                'is_active' => true
            ],
            [
                'campaign_id' => $campaign->id,
                'brand' => 'brand1',
                'question' => 'question3',
                'answer' => 'answer3',
                'answer_type' => 'text',
                'is_active' => true
            ],
        ];

        CampaignQuestion::insert($questions);

        $dbQuestions = CampaignQuestion::where('campaign_id', $campaign->id)
            ->where('is_active', true)
            ->get();

        // user answer each question one by one
        collect($dbQuestions)->each(function ($question, $key) {
            $response = $this->postJson('/api/v1/campaigns/save/single_aswer', [
                'question_id' => $question['id'],
                'answer' => $question['answer']
            ]);

            $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Answer saved successfully'
                ]);

            // assert db has correct records on table campaigns_questions_answers_users
            $this->assertDatabaseHas('campaigns_questions_answers_users', [
                'campaign_question_id' => $question['id'],
                'user_id' => $this->user->id,
                'answer' => $question['answer']
            ]);
        });
    }
}
