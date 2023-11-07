<?php

use Tests\TestCase;
use App\Models\Article;
use App\Models\User;
use App\Models\ViewQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class ViewTest extends TestCase
{
    use RefreshDatabase;
    protected $user;

    public function setUp(): void
    {
        parent::setUp();

        // reset database
        $this->refreshDatabase();

        // mock log in user get token
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user,['*']);
    }

    public function testPostView()
    {
        $data = [
            'viewable_type' => 'article',
            'viewable_id' => 1,
        ];

        $response = $this->postJson('/api/v1/views', $data);

        $response
            ->assertOk()
            ->assertJson(['message' => 'View recorded']);

        $this->assertDatabaseHas('views', [
            'viewable_type' => Article::class,
            'viewable_id' => 1,
        ]);
    }

    public function testGetViews()
    {
        //add data
        $data = [
            'viewable_type' => 'article',
            'viewable_id' => 1,
        ];

        $post_response = $this->postJson('/api/v1/views', $data);

        $type = 'article';
        $id = 1;

        $response = $this->getJson("/api/v1/views/{$type}/{$id}");

        $response
            ->assertOk()
            ->assertJsonStructure([
                'views',
                'total',
            ]);

        $this->assertDatabaseHas('views', [
            'viewable_type' => Article::class,
            'viewable_id' => 1,
        ]);
    }

    public function testGeneratedViewsFollowBellCurve()
    {
        $article_id = 1;

        $this->generateViewsForArticle($article_id);

        // Collect the generated views for the article
        $generatedViews = ViewQueue::where('article_id', $article_id)->get();

        // Define an array to store the expected bell curve values
        //expected values calculated by google sheet, using formula =NORM.DIST(A2, 12, 3, FALSE), A1 column contains the hour from 0-24
        $expectedValues = [0, 5, 38, 180, 547, 1065, 1330, 1065, 547, 180, 38, 5, 0]; 

        // Verify that the generated views match the expected values
        foreach ($generatedViews as $key => $generatedView) {
            $this->assertEquals($expectedValues[$key], $generatedView->scheduled_views);
        }
    }

    // Simulate the view generation for a specific article
    protected function generateViewsForArticle($article_id)
    {
        $auto_view_percentage = 10;

        $total_app_user = 1000;
        $peak_time = 12; // in hours
        $standard_deviation = 3;

        $scale_up_by = 100;

        for ($time = 0; $time <= 24; $time += 2) {
            $view_percentage = $this->bellCurve($time, $peak_time, $standard_deviation); //value too small,if round off will be 0,so need to scale up
            $scaled_view_percentage = $view_percentage * $scale_up_by;

            $scheduled_views = round(($total_app_user * ($auto_view_percentage / 100)) * $scaled_view_percentage);

            ViewQueue::create([
                'article_id' => $article_id,
                'scheduled_views' => $scheduled_views,
                'scheduled_at' => now()->addHours($time),
            ]);
        }
    }

    // Function to calculate the view percentage using a bell curve
    protected function bellCurve($x, $peak, $stdDev)
    {
        $exponent = -0.5 * (($x - $peak) / $stdDev) ** 2;
        return (1 / ($stdDev * sqrt(2 * M_PI))) * exp($exponent);
    }
    
}


