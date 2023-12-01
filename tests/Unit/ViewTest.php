<?php

use Tests\TestCase;
use App\Models\Article;
use App\Models\Setting;
use App\Models\User;
use App\Models\View;
use App\Models\ViewQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

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
        $length = count($scheduledViewsArray);
        $middleIndex = floor($length/2);
        $isPeak = $scheduledViewsArray[$middleIndex] === max($scheduledViewsArray);
        //dd($scheduledViewsArray,array_sum($scheduledViewsArray));

        $isBellCurve = true;
        // Check if values increase up to the middle index and decrease from there
        for ($i = 1; $i <= $length / 2; $i++) {
            $leftIndex = $middleIndex - $i;
            $rightIndex = $middleIndex + $i;

            if (
                $leftIndex >= 0 &&
                $rightIndex < $length &&
                isset($scheduledViewsArray[$leftIndex - 1]) &&
                isset($scheduledViewsArray[$rightIndex + 1]) &&
                ($scheduledViewsArray[$leftIndex] <= $scheduledViewsArray[$leftIndex - 1] ||
                $scheduledViewsArray[$rightIndex] <= $scheduledViewsArray[$rightIndex + 1])
            ) {
                $isBellCurve = false;
                break;
            }
        }

        //dd($scheduledViewsArray, $isPeak, $isBellCurve);
        // Assert that the data has a peak in the middle and is symmetric
        $this->assertTrue($isPeak);
        $this->assertTrue($isBellCurve);
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

    //test for autogenerate views with updated scheduled views
    public function testAutoGenerateViewsUpdatedScheduledViews()
    {
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user,['*']);

        // Create an article
        $article = Article::factory()->create();

        // Create a view queue entry for the article
        ViewQueue::create([
            'article_id' => $article->id,
            'scheduled_views' => 100,
            'updated_scheduled_views' => 50,
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
                if ($record->updated_scheduled_views) {
                    $scheduledViews = $record->updated_scheduled_views;
                }

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

        //check if the number of views generated is equal to the updated scheduled views
        $this->assertEquals($scheduledViews, $article->views()->count());
    }

    //test for update scheduled views
    public function testUpdateScheduledViewsInteractionScore()
    {
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user,['*']);

        $article = Article::factory()->create();

        $this->generateViewsForArticle($article->id);

        $articleInteractionScore = 1;

        $pendingViewQueueRecords = ViewQueue::where('article_id', $article->id)
                            ->where('is_processed', false)
                            ->get();

        foreach ($pendingViewQueueRecords as $record) {
            switch (true) {
                case $articleInteractionScore === 0:
                    $scheduledViews = (int) round($record->scheduled_views * 0.2);
                    break;
                case $articleInteractionScore >= 1 && $articleInteractionScore < 100:
                    $scheduledViews = (int) round($record->scheduled_views * 0.5);
                    break;
                case $articleInteractionScore >= 100 && $articleInteractionScore < 200:
                    $scheduledViews = (int) round($record->scheduled_views * 0.75);
                    break;
                default:
                    // No change on scheduled views
                    $scheduledViews = $record->scheduled_views;
            }

            if ($scheduledViews < 0) {
                $scheduledViews = 0;
            }
        
            $record->update(['updated_scheduled_views' => $scheduledViews]);
        }

        //get the total original scheduled views
        $totalOriginalScheduledViews = 0;
        foreach ($pendingViewQueueRecords as $record) {
            $totalOriginalScheduledViews += $record->scheduled_views;
        }

        //get the total updated scheduled views
        $totalUpdatedScheduledViews = 0;
        foreach ($pendingViewQueueRecords as $record) {
            $totalUpdatedScheduledViews += $record->updated_scheduled_views;
        }

        //check if the total updated scheduled views is less than the total original scheduled views
        $this->assertTrue($totalUpdatedScheduledViews < $totalOriginalScheduledViews);

        // Check if the scheduled views were updated
        $this->assertDatabaseHas('view_queues', [
            'article_id' => $article->id,
            'updated_scheduled_views' => $scheduledViews,
        ]);

        //dd($totalOriginalScheduledViews, $totalUpdatedScheduledViews);
    }

    //test scheduled_at cannot be between 1am and 6am
    public function testScheduledAtNotBetween1amAnd6am()
    {
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user,['*']);

        $article = Article::factory()->create();

        $this->generateViewsForArticle($article->id);

        // Collect the generated views for the article
        $generatedViews = ViewQueue::where('article_id', $article->id)->get();

        //get the scheduled_at for each generated views
        $scheduledAtArray = [];
        foreach ($generatedViews as $view) {
            $scheduledAtArray[] = $view->scheduled_at;
        }
        //dd($scheduledAtArray);

        // Check if the scheduled_at is not between 1am and 6am
        foreach ($generatedViews as $view) {
            $scheduled_at = Carbon::parse($view->scheduled_at);
            $this->assertTrue($scheduled_at >= $scheduled_at->copy()->startOfDay()->addHours(6));
        }
    }

    // Simulate the view generation for a specific article
    protected function generateViewsForArticle($article_id)
    {
        $auto_view_percentage = 10;

        $total_app_user = 500;
        $peak_time = 6; // in hours
        $standard_deviation = 3;

        // Calculate the total area under the bell curve from time 0 to 12
        $total_area_under_curve = 0;

        // Calculate the area under the curve for each 2-hour interval
        for ($time = 0; $time <= 12; $time += 2) {
            $total_area_under_curve += $this->bellCurve($time, $peak_time, $standard_deviation);
        }

        // Calculate the desired total number of scheduled views based on a percentage of total users
        $total_desired_views = round($total_app_user * ($auto_view_percentage / 100));

        $total_accumulated_views = 0;
        for ($time = 0; $time <= 12; $time += 2) {
            $view_percentage = $this->bellCurve($time, $peak_time, $standard_deviation) / $total_area_under_curve;
            $scheduled_views = $total_desired_views * $view_percentage;
            $total_accumulated_views += $scheduled_views;

            // Ensure that the total accumulated views do not exceed the desired total
            $scheduled_views = min($scheduled_views, $total_desired_views - $total_accumulated_views);
            if($scheduled_views < 0) {
                $scheduled_views = 0;
            }

            $scheduled_at = now()->addHours($time);

            // Convert scheduled_at to Malaysia time (UTC+8)
            $scheduled_at = $scheduled_at->setTimezone('Asia/Kuala_Lumpur');
        
            // Check if the scheduled_at falls between 1 am and 2 am
            if ($scheduled_at >= $scheduled_at->copy()->startOfDay()->addHours(1) && $scheduled_at <= $scheduled_at->copy()->startOfDay()->addHours(2)) {
                // Adjust the scheduled_at to be 6 am
                $scheduled_at = $scheduled_at->copy()->startOfDay()->addHours(6);
            } elseif ($scheduled_at >= $scheduled_at->copy()->startOfDay()->addHours(2) && $scheduled_at <= $scheduled_at->copy()->startOfDay()->addHours(3)) {
                // Adjust the scheduled_at to be 7 am
                $scheduled_at = $scheduled_at->copy()->startOfDay()->addHours(7);
            } elseif ($scheduled_at >= $scheduled_at->copy()->startOfDay()->addHours(3) && $scheduled_at <= $scheduled_at->copy()->startOfDay()->addHours(4)) {
                // Adjust the scheduled_at to be 8 am
                $scheduled_at = $scheduled_at->copy()->startOfDay()->addHours(8);
            } elseif ($scheduled_at >= $scheduled_at->copy()->startOfDay()->addHours(4) && $scheduled_at <= $scheduled_at->copy()->startOfDay()->addHours(5)) {
                // Adjust the scheduled_at to be 9 am
                $scheduled_at = $scheduled_at->copy()->startOfDay()->addHours(9);
            } elseif ($scheduled_at >= $scheduled_at->copy()->startOfDay()->addHours(5) && $scheduled_at <= $scheduled_at->copy()->startOfDay()->addHours(6)) {
                // Adjust the scheduled_at to be 10 am
                $scheduled_at = $scheduled_at->copy()->startOfDay()->addHours(10);
            }

            ViewQueue::create([
                'article_id' => $article_id,
                'scheduled_views' => $scheduled_views,
                'scheduled_at' => $scheduled_at,
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


