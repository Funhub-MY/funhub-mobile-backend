<?php

use Tests\TestCase;
use App\Models\Article;
use App\Models\Setting;
use App\Models\User;
use App\Models\View;
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
        $scheduledViewsArray = [];

        foreach ($generatedViews as $view) {
            $scheduledViewsArray[] = $view->scheduled_views;
        }

        // Check if the middle value is the peak
        $middleIndex = floor(count($scheduledViewsArray) / 2);
        $isPeak = $scheduledViewsArray[$middleIndex] === max($scheduledViewsArray);
        //dd($scheduledViewsArray);

        $isSymmetric = true;
        $length = count($scheduledViewsArray);
        for ($i = 0; $i < $length / 2; $i++) {
            if ($scheduledViewsArray[$i] !== $scheduledViewsArray[$length - 1 - $i]) {
                $isSymmetric = false;
                break;
            }
        }
        
        // Assert that the data has a peak in the middle and is symmetric
        $this->assertTrue($isPeak);
        $this->assertTrue($isSymmetric);
    }

    //test for autogenerate views
    public function testAutoGenerateViews()
    {
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user,['*']);

        // Create an article
        $article = Article::factory()->create();

        // Create a view queue entry for the article
        ViewQueue::create([
            'article_id' => $article->id,
            'scheduled_views' => 100,
            'scheduled_at' => now(),
        ]);

        // the command generate:article-views
        $viewQueueRecords = ViewQueue::where('scheduled_at', '<=', now())
                            ->where('is_processed', false)
                            ->get();

        if ($viewQueueRecords->isNotEmpty()) {
            foreach ($viewQueueRecords as $record) {
                $articleId = $record->article_id;
                $scheduledViews = $record->scheduled_views;

                for ($i = 0; $i < $scheduledViews; $i++) {
                    View::create([
                        'user_id' => $this->user->id,
                        'viewable_type' => Article::class,
                        'viewable_id' => $articleId,
                        'ip_address' => null,
                        'is_system_generated' => true,
                    ]);
                }

                $record->update(['is_processed' => true]);
            }
        }

        // Check if the views were generated
        $this->assertDatabaseHas('views', [
            'viewable_type' => Article::class,
            'viewable_id' => $article->id,
        ]);

        // Check if the ViewQueue record was processed
        $this->assertDatabaseHas('view_queues', [
            'article_id' => $article->id,
            'is_processed' => true,
        ]);
    }

    // Simulate the view generation for a specific article
    protected function generateViewsForArticle($article_id)
    {
        $auto_view_percentage = 1;

        $total_app_user = 500;
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


